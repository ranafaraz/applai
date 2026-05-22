<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // =========================================================================
    // Index
    // =========================================================================

    public function test_email_accounts_index_requires_authentication(): void
    {
        $this->get(route('email-accounts.index'))->assertRedirect(route('login'));
    }

    public function test_email_accounts_index_returns_ok(): void
    {
        $this->actingAs($this->user)->get(route('email-accounts.index'))->assertOk();
    }

    public function test_email_accounts_index_only_shows_own_accounts(): void
    {
        $other = User::factory()->create();
        EmailAccount::factory()->create(['user_id' => $this->user->id, 'name' => 'My Account']);
        EmailAccount::factory()->create(['user_id' => $other->id, 'name' => 'Their Account']);

        $response = $this->actingAs($this->user)->get(route('email-accounts.index'));

        $response->assertSee('My Account');
        $response->assertDontSee('Their Account');
    }

    // =========================================================================
    // Create / Store
    // =========================================================================

    public function test_email_account_create_page_is_accessible(): void
    {
        $this->actingAs($this->user)->get(route('email-accounts.create'))->assertOk();
    }

    public function test_user_can_create_an_email_account(): void
    {
        $response = $this->actingAs($this->user)->post(route('email-accounts.store'), [
            'name'            => 'Work Account',
            'email'           => 'work@example.com',
            'from_name'       => 'Work User',
            'smtp_host'       => 'smtp.example.com',
            'smtp_port'       => 587,
            'smtp_encryption' => 'tls',
            'smtp_username'   => 'work@example.com',
            'smtp_password'   => 'smtp-secret',
        ]);

        $response->assertRedirect(route('email-accounts.index'));

        $this->assertDatabaseHas('email_accounts', [
            'user_id' => $this->user->id,
            'name'    => 'Work Account',
            'email'   => 'work@example.com',
        ]);
    }

    public function test_smtp_password_is_stored_encrypted(): void
    {
        $this->actingAs($this->user)->post(route('email-accounts.store'), [
            'name'            => 'Secure Account',
            'email'           => 'secure@example.com',
            'from_name'       => 'Secure User',
            'smtp_host'       => 'smtp.example.com',
            'smtp_port'       => 587,
            'smtp_encryption' => 'tls',
            'smtp_username'   => 'secure@example.com',
            'smtp_password'   => 'my-plain-text-password',
        ]);

        // The raw database value should NOT be the plain-text password
        $account = EmailAccount::where('email', 'secure@example.com')->first();
        $this->assertNotNull($account);

        // The model cast decrypts it transparently, so reading it back gives the plain text
        $this->assertEquals('my-plain-text-password', $account->smtp_password);

        // Ensure the raw DB value is different (encrypted)
        $rawPassword = \Illuminate\Support\Facades\DB::table('email_accounts')
            ->where('id', $account->id)
            ->value('smtp_password');

        $this->assertNotEquals('my-plain-text-password', $rawPassword);
    }

    public function test_store_email_account_requires_name(): void
    {
        $response = $this->actingAs($this->user)->post(route('email-accounts.store'), [
            'email'           => 'test@example.com',
            'from_name'       => 'Test',
            'smtp_host'       => 'smtp.example.com',
            'smtp_port'       => 587,
            'smtp_encryption' => 'tls',
            'smtp_username'   => 'test@example.com',
            'smtp_password'   => 'secret',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_email_account_validates_smtp_port(): void
    {
        $response = $this->actingAs($this->user)->post(route('email-accounts.store'), [
            'name'            => 'Test',
            'email'           => 'test@example.com',
            'from_name'       => 'Test',
            'smtp_host'       => 'smtp.example.com',
            'smtp_port'       => 99999,
            'smtp_encryption' => 'tls',
            'smtp_username'   => 'test@example.com',
            'smtp_password'   => 'secret',
        ]);

        $response->assertSessionHasErrors('smtp_port');
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function test_user_can_view_own_email_account(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get(route('email-accounts.show', $account))->assertOk();
    }

    public function test_user_cannot_view_another_users_email_account(): void
    {
        $other   = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->get(route('email-accounts.show', $account))
            ->assertNotFound();
    }

    // =========================================================================
    // Daily limit tracking
    // =========================================================================

    public function test_email_account_tracks_daily_limit(): void
    {
        $account = EmailAccount::factory()->create([
            'user_id'           => $this->user->id,
            'daily_limit'       => 50,
            'emails_sent_today' => 0,
        ]);

        $this->assertEquals(0, $account->emails_sent_today);
        $this->assertEquals(50, $account->daily_limit);
        $this->assertEquals(0.0, $account->daily_usage_percent);
    }

    public function test_daily_usage_percent_is_calculated_correctly(): void
    {
        $account = EmailAccount::factory()->make([
            'daily_limit'       => 100,
            'emails_sent_today' => 25,
        ]);

        $this->assertEquals(25.0, $account->daily_usage_percent);
    }

    public function test_daily_usage_percent_is_zero_when_limit_is_zero(): void
    {
        $account = EmailAccount::factory()->make([
            'daily_limit'       => 0,
            'emails_sent_today' => 0,
        ]);

        $this->assertEquals(0.0, $account->daily_usage_percent);
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    public function test_user_can_delete_own_email_account(): void
    {
        $account = EmailAccount::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->delete(route('email-accounts.destroy', $account))
            ->assertRedirect(route('email-accounts.index'));

        $this->assertSoftDeleted('email_accounts', ['id' => $account->id]);
    }

    public function test_user_cannot_delete_another_users_email_account(): void
    {
        $other   = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->delete(route('email-accounts.destroy', $account))
            ->assertNotFound();
    }
}
