<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\ApiClientToken;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostConfirmation;
use App\Models\SocialPostTarget;
use App\Models\SocialProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Social\SocialPublisherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the GPT/API schedule-confirmation handoff: approving a `schedule`
 * confirmation must leave the post + a target in the `scheduled` state that
 * social:publish-due-posts consumes (previously the approval was recorded
 * but no scheduled target was ever produced, so the post never published).
 */
class SocialScheduleConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SocialAccount $account;
    private string $rawToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name'   => 'Schedule Confirmation Tenant',
            'slug'   => 'schedule-confirmation-' . uniqid(),
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'admin',
        ]);

        $provider = SocialProvider::updateOrCreate(
            ['key' => 'linkedin'],
            ['name' => 'LinkedIn', 'status' => 'enabled'],
        );

        $this->account = SocialAccount::create([
            'tenant_id'               => $tenant->id,
            'user_id'                 => $this->user->id,
            'provider_id'             => $provider->id,
            'provider_account_urn'    => 'urn:li:person:TEST123',
            'display_name'            => 'Test LinkedIn',
            'access_token_encrypted'  => 'test-token',
            'status'                  => 'connected',
            'is_default'              => true,
        ]);

        $client = ApiClient::create([
            'user_id'     => $this->user->id,
            'name'        => 'Test GPT Client',
            'source_type' => 'custom_gpt',
            'scopes'      => ['social:read', 'social:publish'],
            'is_active'   => true,
        ]);

        $token = ApiClientToken::generateRaw('test');
        $this->rawToken = $token['raw'];

        ApiClientToken::create([
            'api_client_id' => $client->id,
            'user_id'       => $this->user->id,
            'name'          => 'Test Token',
            'token_hash'    => $token['hash'],
            'token_prefix'  => $token['prefix'],
            'is_active'     => true,
        ]);
    }

    private function makePost(): SocialPost
    {
        return SocialPost::create([
            'tenant_id'       => $this->user->tenant_id,
            'user_id'         => $this->user->id,
            'title_internal'  => 'API Scheduled Post',
            'post_body'       => 'Hello from the API scheduling flow.',
            'post_type'       => 'text',
            'status'          => 'ready_for_review',
            'approval_status' => 'pending_review',
            'created_source'  => 'chatgpt',
            'content_version' => 1,
        ]);
    }

    private function approveConfirmation(SocialPostConfirmation $confirmation)
    {
        return $this->postJson(
            "/api/social/v1/linkedin/confirmations/{$confirmation->confirmation_token}/approve",
            [],
            ['X-Api-Key' => $this->rawToken],
        );
    }

    public function test_approving_schedule_confirmation_creates_scheduled_target(): void
    {
        $post = $this->makePost();
        $scheduledAt = now()->addHours(3)->startOfMinute();

        $confirmation = SocialPostConfirmation::createFor(
            $post, 'schedule', $scheduledAt->toDateTimeString(), 'UTC',
        );

        $this->approveConfirmation($confirmation)
            ->assertOk()
            ->assertJsonPath('action', 'schedule');

        $post->refresh();
        $this->assertSame('approved', $post->approval_status);
        $this->assertSame('scheduled', $post->status);
        $this->assertTrue($post->scheduled_at->equalTo($scheduledAt));

        $target = $post->targets()->first();
        $this->assertNotNull($target, 'A SocialPostTarget must be created for the scheduled post.');
        $this->assertSame('scheduled', $target->status);
        $this->assertSame('linkedin', $target->provider_key);
        $this->assertSame($this->account->id, $target->social_account_id);
        $this->assertTrue($target->scheduled_at->equalTo($scheduledAt));

        $this->assertSame('used', $confirmation->fresh()->status);
    }

    public function test_publish_due_posts_picks_up_api_scheduled_target(): void
    {
        $post = $this->makePost();
        $confirmation = SocialPostConfirmation::createFor(
            $post, 'schedule', now()->addHour()->toDateTimeString(), 'UTC',
        );

        $this->approveConfirmation($confirmation)->assertOk();

        $target = $post->targets()->firstOrFail();

        $this->mock(SocialPublisherService::class, function ($mock) use ($target) {
            $mock->shouldReceive('publish')
                ->once()
                ->withArgs(fn (SocialPostTarget $t) => $t->id === $target->id)
                ->andReturnUsing(function (SocialPostTarget $t) {
                    $t->update(['status' => 'published', 'published_at' => now()]);
                    return $t;
                });
        });

        $this->travel(2)->hours();

        $this->artisan('social:publish-due-posts')->assertExitCode(0);

        $this->assertSame('published', $target->fresh()->status);
    }

    public function test_schedule_confirmation_without_connected_account_returns_422(): void
    {
        $this->account->update(['status' => 'disconnected']);

        $post = $this->makePost();
        $confirmation = SocialPostConfirmation::createFor(
            $post, 'schedule', now()->addHour()->toDateTimeString(), 'UTC',
        );

        $this->approveConfirmation($confirmation)
            ->assertStatus(422)
            ->assertJsonPath('error', 'No connected LinkedIn account available to schedule this post.');

        $this->assertSame(0, $post->targets()->count());
    }

    public function test_rejecting_schedule_confirmation_leaves_post_untouched(): void
    {
        $post = $this->makePost();
        $confirmation = SocialPostConfirmation::createFor(
            $post, 'schedule', now()->addHour()->toDateTimeString(), 'UTC',
        );

        $this->postJson(
            "/api/social/v1/linkedin/confirmations/{$confirmation->confirmation_token}/reject",
            [],
            ['X-Api-Key' => $this->rawToken],
        )->assertOk();

        $post->refresh();
        $this->assertSame('pending_review', $post->approval_status);
        $this->assertSame('ready_for_review', $post->status);
        $this->assertSame(0, $post->targets()->count());
    }
}
