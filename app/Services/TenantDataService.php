<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Models\Contact;
use App\Models\Document;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailSignature;
use App\Models\EmailTemplate;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\SocialMediaAsset;
use App\Models\SocialPost;
use App\Models\SuppressionList;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * GDPR tooling: full tenant data export (export-anytime, all plans) and
 * hard deletion of a tenant and everything it owns.
 */
class TenantDataService
{
    public const EXPORT_DISK = 'local';
    public const EXPORT_DIR  = 'exports';

    /** Datasets included in exports, keyed by file name. */
    private function datasets(Tenant $tenant): array
    {
        $byTenant = fn (string $model) => $model::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->get();

        return [
            'contacts'         => fn () => Contact::withTrashed()->where('tenant_id', $tenant->id)->get(),
            'opportunities'    => fn () => Opportunity::withTrashed()->where('tenant_id', $tenant->id)->get(),
            'email_messages'   => fn () => $byTenant(EmailMessage::class),
            'inbox_messages'   => fn () => $byTenant(InboxMessage::class),
            'follow_ups'       => fn () => $byTenant(FollowUp::class),
            'email_templates'  => fn () => $byTenant(EmailTemplate::class),
            'email_signatures' => fn () => $byTenant(EmailSignature::class),
            'social_posts'     => fn () => $byTenant(SocialPost::class),
            'suppression_list' => fn () => $byTenant(SuppressionList::class),
            'tags'             => fn () => $byTenant(Tag::class),
            'documents'        => fn () => $byTenant(Document::class),
            'users'            => fn () => User::where('tenant_id', $tenant->id)
                ->get()->makeVisible([])->map(fn ($u) => collect($u->toArray())->except(['password', 'remember_token'])),
        ];
    }

    /**
     * Build a ZIP containing every dataset as JSON + CSV. Returns the path
     * relative to the export disk.
     */
    public function export(Tenant $tenant): string
    {
        Storage::disk(self::EXPORT_DISK)->makeDirectory(self::EXPORT_DIR);

        $relative = self::EXPORT_DIR . '/tenant-' . $tenant->id . '-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.zip';
        $absolute = Storage::disk(self::EXPORT_DISK)->path($relative);

        $zip = new ZipArchive();
        $zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($this->datasets($tenant) as $name => $loader) {
            $rows = collect($loader())->map(
                fn ($row) => is_array($row) || $row instanceof Collection ? collect($row)->toArray() : $row->toArray()
            );

            $zip->addFromString("{$name}.json", json_encode($rows->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->addFromString("{$name}.csv", $this->toCsv($rows));
        }

        $zip->addFromString('README.txt', implode("\n", [
            'Data export for: ' . $tenant->name,
            'Generated at: ' . now()->toIso8601String(),
            '',
            'Each dataset is included as both JSON (full fidelity) and CSV.',
        ]));

        $zip->close();

        return $relative;
    }

    /**
     * Hard-delete the tenant and everything it owns: rows (including
     * soft-deleted), stored files, API credentials, users, and the Paddle
     * subscription. Irreversible.
     */
    public function purge(Tenant $tenant): void
    {
        $userIds = User::where('tenant_id', $tenant->id)->pluck('id');

        // Cancel billing first so no further charges occur.
        try {
            $tenant->subscription()?->cancelNow();
        } catch (\Throwable $e) {
            Log::warning('Tenant purge: could not cancel Paddle subscription', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
        }

        // Stored files.
        Document::where('tenant_id', $tenant->id)->pluck('file_path')
            ->merge(SocialMediaAsset::whereIn('user_id', $userIds)->pluck('file_path'))
            ->filter()
            ->each(fn ($path) => Storage::disk('local')->delete($path));

        // API access.
        $clientIds = ApiClient::whereIn('user_id', $userIds)->pluck('id');
        DB::table('api_client_tokens')->whereIn('api_client_id', $clientIds)->delete();
        DB::table('api_request_logs')->whereIn('api_client_id', $clientIds)->delete();
        DB::table('ai_action_audit_logs')->whereIn('api_client_id', $clientIds)->delete();
        ApiClient::whereIn('id', $clientIds)->delete();

        // Tenant-scoped rows. Children with cascading FKs (attachments,
        // import rows, social post targets, subscription items, ...) are
        // removed by the parent deletes.
        $tenantScoped = [
            'follow_ups', 'inbox_messages', 'email_messages', 'documents',
            'email_templates', 'email_signatures', 'email_accounts',
            'timeline_events', 'suppression_list', 'tags',
            'contact_imports', 'opportunity_imports',
            'social_activity_logs',
            'contacts', 'opportunities',
        ];

        foreach ($tenantScoped as $table) {
            DB::table($table)->where('tenant_id', $tenant->id)->delete();
        }

        DB::table('social_analytics_snapshots')->whereIn(
            'social_account_id',
            DB::table('social_accounts')->whereIn('user_id', $userIds)->pluck('id'),
        )->delete();

        $userScoped = [
            'social_audit_events'      => 'user_id',
            'social_post_confirmations' => 'user_id',
            'social_posts'             => 'user_id',
            'social_media_assets'      => 'user_id',
            'social_accounts'          => 'user_id',
            'social_oauth_apps'        => 'user_id',
            'api_attachments'          => 'user_id',
            'user_settings'            => 'user_id',
            'notifications'            => 'notifiable_id',
        ];

        foreach ($userScoped as $table => $column) {
            DB::table($table)->whereIn($column, $userIds)->delete();
        }

        DB::table('activity_log')->whereIn('causer_id', $userIds)->where('causer_type', User::class)->delete();

        User::where('tenant_id', $tenant->id)->delete();
        $tenant->customer()?->delete();
        $tenant->delete();
    }

    /** Render a collection of row arrays as CSV (header row from union of keys). */
    private function toCsv(Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return '';
        }

        $headers = $rows->reduce(
            fn (array $carry, $row) => array_unique(array_merge($carry, array_keys($row))),
            [],
        );

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn ($key) => $this->scalarize($row[$key] ?? null),
                $headers,
            ));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function scalarize(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
