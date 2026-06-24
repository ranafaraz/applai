<?php

namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile API (/api/app/v1) auth round-trip + failure modes (Milestone 1).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Sup3rSecret!';

    private function register(string $email = 'jordan@example.com'): array
    {
        return $this->postJson('/api/app/v1/auth/register', [
            'full_name'             => 'Jordan Doe',
            'email'                 => $email,
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'tracking_types'        => ['job', 'phd'],
        ])->json();
    }

    public function test_register_login_refresh_me_happy_path(): void
    {
        $res = $this->postJson('/api/app/v1/auth/register', [
            'full_name'             => 'Jordan Doe',
            'email'                 => 'jordan@example.com',
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        $res->assertStatus(201)
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in', 'user' => ['id', 'full_name', 'email', 'initials']])
            ->assertJsonPath('user.full_name', 'Jordan Doe');

        // Each registrant gets their own tenant for data isolation.
        $this->assertDatabaseHas('users', ['email' => 'jordan@example.com', 'role' => 'admin']);
        $user = User::where('email', 'jordan@example.com')->first();
        $this->assertNotNull($user->tenant_id);

        $login = $this->postJson('/api/app/v1/auth/login', [
            'email'    => 'jordan@example.com',
            'password' => self::PASSWORD,
        ]);
        $login->assertOk()->assertJsonPath('user.email', 'jordan@example.com');
        $token = $login->json('access_token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/app/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'jordan@example.com');

        $this->postJson('/api/app/v1/auth/refresh', ['refresh_token' => $res->json('refresh_token')])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token']);
    }

    public function test_refresh_token_rotates_and_old_one_is_rejected(): void
    {
        $reg     = $this->register();
        $refresh = $reg['refresh_token'];

        $this->postJson('/api/app/v1/auth/refresh', ['refresh_token' => $refresh])->assertOk();

        // The rotated (old) token must no longer work.
        $this->postJson('/api/app/v1/auth/refresh', ['refresh_token' => $refresh])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_duplicate_email_returns_field_level_error(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/app/v1/auth/register', [
            'full_name'             => 'X',
            'email'                 => 'taken@example.com',
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertStatus(422)->assertJsonPath('errors.email.0', 'Email already in use.');
    }

    public function test_bad_password_returns_401(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);

        $this->postJson('/api/app/v1/auth/login', [
            'email'    => 'a@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_forgot_always_returns_200(): void
    {
        $this->postJson('/api/app/v1/auth/forgot', ['email' => 'nobody@example.com'])->assertOk();
    }

    public function test_unauthenticated_me_is_401(): void
    {
        $this->getJson('/api/app/v1/auth/me')->assertStatus(401);
    }

    public function test_social_login_is_gated_off_for_v1(): void
    {
        $this->postJson('/api/app/v1/auth/social', ['provider' => 'google', 'id_token' => 'x'])
            ->assertStatus(501)
            ->assertJsonPath('code', 'NOT_IMPLEMENTED');
    }

    public function test_delete_account_soft_deletes_and_revokes_token(): void
    {
        $reg   = $this->register();
        $token = $reg['access_token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/app/v1/auth/account', ['password' => self::PASSWORD])
            ->assertOk();

        $this->assertSoftDeleted('users', ['email' => 'jordan@example.com']);

        // The Sanctum RequestGuard memoizes the resolved user on the singleton
        // guard across requests in a single test process; flush it so the next
        // request re-resolves from the (now-deleted) token, as a real HTTP
        // process would.
        $this->app['auth']->forgetGuards();

        // Revoked token must no longer authenticate.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/app/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $reg   = $this->register();
        $token = $reg['access_token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/app/v1/auth/change-password', [
                'current_password'          => 'wrong',
                'new_password'              => 'BrandN3wPass!',
                'new_password_confirmation' => 'BrandN3wPass!',
            ])->assertStatus(422)->assertJsonPath('code', 'INVALID_PASSWORD');
    }
}
