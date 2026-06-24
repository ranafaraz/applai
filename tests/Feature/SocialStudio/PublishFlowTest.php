<?php

namespace Tests\Feature\SocialStudio;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\SocialProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression tests for the three Social Studio publish bugs:
 *   Bug 1 — LinkedIn API version must be YYYYMM and env-configurable
 *   Bug 2 — UI must not flash success when the provider call failed
 *   Bug 3 — Post list must not render raw HTML tags
 */
class PublishFlowTest extends TestCase
{
    use RefreshDatabase;

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function user(): User
    {
        $tenant = Tenant::create([
            'name'   => 'Publish Flow Tenant',
            'slug'   => 'publish-flow-' . uniqid(),
            'status' => 'active',
        ]);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'admin',
        ]);
    }

    private function linkedInAccount(User $user): SocialAccount
    {
        $provider = SocialProvider::updateOrCreate(
            ['key' => 'linkedin'],
            ['name' => 'LinkedIn', 'status' => 'enabled', 'capabilities_json' => ['text']],
        );

        return SocialAccount::create([
            'tenant_id'               => $user->tenant_id,
            'user_id'                 => $user->id,
            'provider_id'             => $provider->id,
            'provider_account_urn'    => 'urn:li:person:TESTPERSON123',
            'display_name'            => 'Test User',
            'access_token_encrypted'  => 'fake-token',
            'status'                  => 'connected',
            // token_expires_at null → isTokenExpired() returns false
        ]);
    }

    private function approvedPost(User $user, SocialAccount $account): array
    {
        $post = SocialPost::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'title_internal'  => 'Test LinkedIn Post',
            'post_body'       => '<div>Hello LinkedIn</div>',
            'post_type'       => 'text',
            'status'          => 'approved',
            'approval_status' => 'approved',
        ]);

        $target = SocialPostTarget::create([
            'social_post_id'   => $post->id,
            'social_account_id'=> $account->id,
            'provider_key'     => 'linkedin',
            'platform_body'    => 'Hello LinkedIn',
            'status'           => 'approved',
        ]);

        return [$post, $target];
    }

    // ── Bug 1: LinkedIn-Version header ────────────────────────────────────────

    /** @test */
    public function linkedin_version_header_is_yyyymm_format(): void
    {
        config(['services.linkedin.api_version' => '202506']);

        Http::fake([
            'https://api.linkedin.com/*' => Http::response([], 201, ['x-restli-id' => 'urn:li:share:123456789']),
        ]);

        $user    = $this->user();
        $account = $this->linkedInAccount($user);
        [$post]  = $this->approvedPost($user, $account);

        $this->actingAs($user)
            ->post(route('social-studio.posts.publish-now', $post->id), ['confirm' => '1']);

        Http::assertSent(function ($request) {
            $version = $request->header('Linkedin-Version')[0] ?? '';
            // Must be exactly 6 digits in YYYYMM format
            return preg_match('/^\d{6}$/', $version) === 1;
        });
    }

    /** @test */
    public function linkedin_version_header_equals_configured_env_value(): void
    {
        config(['services.linkedin.api_version' => '202506']);

        Http::fake([
            'https://api.linkedin.com/*' => Http::response([], 201, ['x-restli-id' => 'urn:li:share:123456789']),
        ]);

        $user    = $this->user();
        $account = $this->linkedInAccount($user);
        [$post]  = $this->approvedPost($user, $account);

        $this->actingAs($user)
            ->post(route('social-studio.posts.publish-now', $post->id), ['confirm' => '1']);

        Http::assertSent(fn ($request) => ($request->header('Linkedin-Version')[0] ?? '') === '202506');
    }

    // ── Bug 2: Honest publish result ──────────────────────────────────────────

    /** @test */
    public function publish_now_redirects_to_published_page_on_success(): void
    {
        config(['services.linkedin.api_version' => '202506']);

        Http::fake([
            'https://api.linkedin.com/*' => Http::response([], 201, ['x-restli-id' => 'urn:li:share:999888777']),
        ]);

        $user    = $this->user();
        $account = $this->linkedInAccount($user);
        [$post, $target] = $this->approvedPost($user, $account);

        $response = $this->actingAs($user)
            ->post(route('social-studio.posts.publish-now', $post->id), ['confirm' => '1']);

        $response->assertRedirect(route('social-studio.published'));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('social_post_targets', [
            'id'             => $target->id,
            'status'         => 'published',
            'remote_post_id' => 'urn:li:share:999888777',
        ]);
        $this->assertDatabaseHas('social_posts', ['id' => $post->id, 'status' => 'published']);
    }

    /** @test */
    public function publish_now_shows_error_and_no_success_when_linkedin_returns_426(): void
    {
        config(['services.linkedin.api_version' => '202506']);

        Http::fake([
            'https://api.linkedin.com/*' => Http::response(
                ['status' => 426, 'code' => 'NONEXISTENT_VERSION', 'message' => 'Requested version 202506 is not active'],
                426
            ),
        ]);

        $user    = $this->user();
        $account = $this->linkedInAccount($user);
        [$post, $target] = $this->approvedPost($user, $account);

        $response = $this->actingAs($user)
            ->post(route('social-studio.posts.publish-now', $post->id), ['confirm' => '1']);

        // Must NOT redirect to published page or flash success
        $response->assertSessionMissing('success');
        $response->assertSessionHas('error');

        // Per-target must be stored as failed with the error payload
        $this->assertDatabaseHas('social_post_targets', [
            'id'     => $target->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('social_posts', ['id' => $post->id, 'status' => 'failed']);

        // Error message must mention the version expiry
        $errorMsg = session('error') ?? '';
        $this->assertStringContainsString('LINKEDIN_API_VERSION', $errorMsg);
    }

    /** @test */
    public function publish_now_stores_error_message_on_target_for_any_provider_failure(): void
    {
        config(['services.linkedin.api_version' => '202506']);

        Http::fake([
            'https://api.linkedin.com/*' => Http::response(
                ['status' => 401, 'message' => 'Token invalid'],
                401
            ),
        ]);

        $user    = $this->user();
        $account = $this->linkedInAccount($user);
        [$post, $target] = $this->approvedPost($user, $account);

        $this->actingAs($user)
            ->post(route('social-studio.posts.publish-now', $post->id), ['confirm' => '1']);

        $target->refresh();
        $this->assertSame('failed', $target->status);
        $this->assertNotNull($target->error_message);
    }

    // ── Bug 3: No raw HTML in list view ───────────────────────────────────────

    /** @test */
    public function post_list_never_renders_raw_html_tags_in_body_preview(): void
    {
        $user = $this->user();
        $post = SocialPost::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'title_internal'  => 'HTML Tag Test Post',
            'post_body'       => 'A US court just made it official: if AI alone made it, nobody owns it.<div><br></div><div>More content here.</div>',
            'post_type'       => 'text',
            'status'          => 'draft',
            'approval_status' => 'pending_review',
        ]);

        $response = $this->actingAs($user)
            ->get(route('social-studio.posts.index'));

        $response->assertOk();
        $response->assertSee('HTML Tag Test Post');

        // The preview must not contain raw HTML tag literals
        $response->assertDontSee('<div>', false);
        $response->assertDontSee('<br>', false);
        $response->assertDontSee('</div>', false);

        // Plain text content must appear
        $response->assertSee('A US court just made it official');
    }
}
