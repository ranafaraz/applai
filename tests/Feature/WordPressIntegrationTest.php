<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialMediaAsset;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\SocialProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Social\SocialPublisherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WordPressIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_connect_wordpress_site_with_application_password(): void
    {
        Http::fake([
            'https://blog.example.com/wp-json/wp/v2/users/me' => Http::response([
                'id' => 7,
                'name' => 'Editor',
            ]),
        ]);

        $user = $this->user();

        $this->actingAs($user)->post(route('social-studio.connections.wordpress.store'), [
            'site_url' => 'https://blog.example.com',
            'label' => 'Company Blog',
            'username' => 'editor',
            'application_password' => 'abcd efgh ijkl mnop',
        ])->assertRedirect(route('social-studio.connections'));

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_account_urn' => 'https://blog.example.com',
            'display_name' => 'Company Blog',
            'status' => 'connected',
        ]);
    }

    public function test_post_can_target_multiple_wordpress_sites_with_different_metadata(): void
    {
        $user = $this->user();
        $provider = SocialProvider::where('key', 'wordpress')->firstOrFail();
        $first = $this->wordpressAccount($user, $provider, 'https://one.example.com', 'One Blog');
        $second = $this->wordpressAccount($user, $provider, 'https://two.example.com', 'Two Blog');

        $this->actingAs($user)->post(route('social-studio.posts.store'), [
            'title_internal' => 'Internal CRM Launch',
            'post_body' => '<p>Shared content</p>',
            'post_type' => 'text',
            'target_accounts' => [$first->id, $second->id],
            'target_meta' => [
                $first->id => [
                    'title' => 'First title',
                    'content' => '<p>First body</p>',
                    'wp_status' => 'draft',
                ],
                $second->id => [
                    'title' => 'Second title',
                    'content' => '<p>Second body</p>',
                    'wp_status' => 'publish',
                ],
            ],
        ])->assertRedirect();

        $post = SocialPost::firstOrFail();

        $this->assertCount(2, $post->targets);
        $this->assertDatabaseHas('social_post_targets', [
            'social_post_id' => $post->id,
            'social_account_id' => $first->id,
            'provider_key' => 'wordpress',
            'platform_body' => '<p>First body</p>',
        ]);
        $this->assertSame('Second title', $post->targets()->where('social_account_id', $second->id)->first()->platform_metadata_json['title']);
    }

    public function test_wordpress_publisher_uploads_images_and_creates_post(): void
    {
        Storage::fake('public');
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/media')) {
                return Http::response([
                    'id' => 44,
                    'source_url' => 'https://blog.example.com/wp-content/uploads/image.jpg',
                ]);
            }

            if (str_contains($request->url(), '/posts')) {
                return Http::response([
                    'id' => 91,
                    'link' => 'https://blog.example.com/test-post/',
                    'status' => 'publish',
                ]);
            }

            return Http::response([], 404);
        });

        $user = $this->user();
        $provider = SocialProvider::where('key', 'wordpress')->firstOrFail();
        $account = $this->wordpressAccount($user, $provider, 'https://blog.example.com', 'Blog');

        Storage::disk('public')->put('social-media/test.jpg', 'fake-image');
        $asset = SocialMediaAsset::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
            'storage_path' => 'social-media/test.jpg',
            'alt_text' => 'Test image',
            'rights_status' => 'owned',
            'approval_status' => 'approved',
        ]);

        $post = SocialPost::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'title_internal' => 'Test Post',
            'post_body' => '<p>Body</p><p><img src="/storage/test.jpg" data-social-asset-id="' . $asset->id . '" alt="Test image"></p>',
            'post_type' => 'image',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
        $post->mediaAssets()->attach($asset->id, ['is_featured' => true, 'display_order' => 0]);

        $target = SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'provider_key' => 'wordpress',
            'platform_body' => $post->post_body,
            'platform_metadata_json' => [
                'title' => 'WP Title',
                'wp_status' => 'publish',
                'featured_asset_id' => $asset->id,
            ],
            'status' => 'approved',
        ]);

        app(SocialPublisherService::class)->publish($target);

        $target->refresh();
        $this->assertSame('published', $target->status);
        $this->assertSame('91', $target->remote_post_id);
        $this->assertSame('https://blog.example.com/test-post/', $target->remote_post_url);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/posts')
            && $request['title'] === 'WP Title'
            && $request['featured_media'] === 44
            && str_contains($request['content'], 'https://blog.example.com/wp-content/uploads/image.jpg'));
    }

    private function user(): User
    {
        $tenant = Tenant::create([
            'name' => 'WordPress Test Tenant',
            'slug' => 'wordpress-test-tenant-' . uniqid(),
            'status' => 'active',
        ]);

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
    }

    private function wordpressAccount(User $user, SocialProvider $provider, string $siteUrl, string $label): SocialAccount
    {
        return SocialAccount::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'provider_account_urn' => $siteUrl,
            'display_name' => $label,
            'public_profile_url' => $siteUrl,
            'access_token_encrypted' => 'abcd efgh ijkl mnop',
            'status' => 'connected',
            'metadata_json' => [
                'site_url' => $siteUrl,
                'api_base' => "{$siteUrl}/wp-json/wp/v2",
                'username' => 'editor',
            ],
        ]);
    }
}
