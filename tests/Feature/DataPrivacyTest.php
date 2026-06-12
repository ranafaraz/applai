<?php

namespace Tests\Feature;

use App\Jobs\ExportTenantDataJob;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

class DataPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_builds_zip_with_datasets(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Contact::factory()->count(2)->create(['user_id' => $user->id, 'tenant_id' => $user->tenant_id]);
        Opportunity::factory()->create(['user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'title' => 'Exported Opp']);

        $path = app(TenantDataService::class)->export($user->tenant);

        $absolute = Storage::disk(TenantDataService::EXPORT_DISK)->path($path);
        $this->assertFileExists($absolute);

        $zip = new ZipArchive();
        $zip->open($absolute);
        $contacts = json_decode($zip->getFromName('contacts.json'), true);
        $opps     = json_decode($zip->getFromName('opportunities.json'), true);
        $this->assertCount(2, $contacts);
        $this->assertSame('Exported Opp', $opps[0]['title']);
        $this->assertNotFalse($zip->getFromName('contacts.csv'));
        $this->assertNotFalse($zip->getFromName('users.json'));
        $this->assertStringNotContainsString('password', $zip->getFromName('users.json'));
        $zip->close();

        Storage::disk(TenantDataService::EXPORT_DISK)->delete($path);
    }

    public function test_export_request_dispatches_job(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post(route('settings.data-export'))
            ->assertRedirect();

        Queue::assertPushed(ExportTenantDataJob::class);
    }

    public function test_download_rejects_other_tenants_exports(): void
    {
        $user  = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);

        $file = 'tenant-' . $other->tenant_id . '-20260101-000000-abc123.zip';
        $url  = URL::temporarySignedRoute('data-export.download', now()->addDay(), ['file' => $file]);

        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post(route('settings.delete-account'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertNotSame('cancelled', $user->tenant->fresh()->status);
    }

    public function test_account_deletion_marks_tenant_and_logs_out(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post(route('settings.delete-account'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        $tenant = $user->tenant->fresh();
        $this->assertSame('cancelled', $tenant->status);
        $this->assertNotNull($tenant->deletion_requested_at);
        $this->assertGuest();
    }

    public function test_purge_command_removes_tenant_data_after_grace(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Contact::factory()->count(3)->create(['user_id' => $user->id, 'tenant_id' => $user->tenant_id]);
        $tenantId = $user->tenant_id;

        $user->tenant->update([
            'status'                => 'cancelled',
            'deletion_requested_at' => now()->subDays(31),
        ]);

        $this->artisan('tenants:purge-deleted')->assertExitCode(0);

        $this->assertDatabaseMissing('tenants', ['id' => $tenantId]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame(0, Contact::where('tenant_id', $tenantId)->count());
    }

    public function test_purge_command_respects_grace_period(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $user->tenant->update([
            'status'                => 'cancelled',
            'deletion_requested_at' => now()->subDays(5),
        ]);

        $this->artisan('tenants:purge-deleted')->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['id' => $user->tenant_id]);
    }
}
