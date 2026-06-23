<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Models\ApiClientToken;
use Illuminate\Console\Command;

class ExpandTokenCommand extends Command
{
    protected $signature = 'crm:expand-token
        {token_id : ApiClientToken ID (or use --prefix)}
        {--prefix= : Resolve the token by its token_prefix instead of ID}
        {--dry-run : Show the resulting scope set without saving}';

    protected $description = 'Expand the scopes on the ApiClient backing a token to the full agent-backend scope set (additive, idempotent).';

    /**
     * Canonical full scope set for the Claude agent-backend integration.
     * Scopes are stored on ApiClient (see CheckApiClientScope / ApiClient::hasScope).
     */
    public const FULL_SCOPES = [
        // Existing
        'dashboard:read',
        'contacts:read', 'contacts:write', 'contacts:delete',
        'opportunities:read', 'opportunities:write', 'opportunities:delete',
        'documents:read', 'documents:write',
        'notes:write',
        'drafts:read', 'drafts:create', 'drafts:update', 'drafts:delete',
        'followups:read', 'followups:create', 'followups:update', 'followups:delete',
        'replies:read',
        'signatures:read', 'signatures:write',
        'attachments:read', 'attachments:write',
        'social:read', 'social:write', 'social:publish', 'social:analytics',
        // New
        'email:send',
        'bulk:write',
        'tags:read', 'tags:write',
        'content:read', 'content:write', 'content:publish',
        'youtube:read', 'youtube:write',
        'research:read', 'research:write',
        'proposals:read', 'proposals:write',
        'freelance:read', 'freelance:write',
        'pipelines:read', 'pipelines:write', 'pipelines:execute',
        'scheduler:read', 'scheduler:write',
        'webhooks:read', 'webhooks:write',
        'analytics:read',
    ];

    public function handle(): int
    {
        $token = $this->option('prefix')
            ? ApiClientToken::where('token_prefix', $this->option('prefix'))->first()
            : ApiClientToken::find($this->argument('token_id'));

        if (! $token) {
            $this->error('Token not found.');
            return self::FAILURE;
        }

        /** @var ApiClient|null $client */
        $client = $token->apiClient;
        if (! $client) {
            $this->error("Token #{$token->id} has no associated ApiClient.");
            return self::FAILURE;
        }

        $existing = $client->scopes ?? [];

        // Union existing + full set, preserving order: full set first, then any extras.
        $merged = array_values(array_unique(array_merge(self::FULL_SCOPES, $existing)));
        $added  = array_values(array_diff($merged, $existing));

        $this->info("Token #{$token->id} (prefix {$token->token_prefix}) → ApiClient #{$client->id} \"{$client->name}\"");
        $this->line('  current scopes: ' . count($existing));
        $this->line('  resulting scopes: ' . count($merged));
        $this->line('  newly added: ' . (count($added) ? implode(', ', $added) : '(none — already complete)'));

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes saved.');
            return self::SUCCESS;
        }

        $client->scopes = $merged;
        $client->save();

        $this->info("✓ ApiClient #{$client->id} now has " . count($merged) . ' scopes.');

        return self::SUCCESS;
    }
}
