<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanLimitsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $plan = 'free', string $status = 'active', int $maxUsers = 1): Tenant
    {
        return Tenant::create([
            'name'      => 'Limits Tenant',
            'slug'      => 'limits-' . uniqid(),
            'plan'      => $plan,
            'status'    => $status,
            'max_users' => $maxUsers,
        ]);
    }

    private function admin(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    }

    public function test_free_plan_blocks_contact_creation_at_limit(): void
    {
        $tenant = $this->tenant();
        $user   = $this->admin($tenant);

        $limit = config('plans.plans.free.limits.contacts');
        Contact::factory()->count($limit)->create([
            'user_id'   => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($user)->post(route('contacts.store'), [
            'first_name' => 'Over',
            'last_name'  => 'Limit',
            'email'      => 'over-limit@example.com',
        ]);

        $response->assertSessionHasErrors('plan');
        $this->assertDatabaseMissing('contacts', ['email' => 'over-limit@example.com']);
    }

    public function test_trial_tenant_gets_pro_limits(): void
    {
        $tenant = $this->tenant('free', 'trial');
        $tenant->update(['trial_ends_at' => now()->addDays(10)]);
        $user = $this->admin($tenant);

        $limits = app(PlanLimitsService::class);

        $this->assertSame('pro', $limits->effectivePlanKey($tenant));
        $this->assertNull($limits->limit($tenant, 'contacts'));
        $this->assertTrue($limits->hasFeature($tenant, 'follow_up_automation'));
    }

    public function test_free_plan_blocks_second_email_account(): void
    {
        $tenant = $this->tenant();
        $user   = $this->admin($tenant);

        EmailAccount::factory()->create([
            'user_id'   => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($user)->post(route('email-accounts.store'), [
            'name'          => 'Second Account',
            'email'         => 'second@example.com',
            'from_name'     => 'Second',
            'smtp_host'       => 'smtp.example.com',
            'smtp_port'       => 587,
            'smtp_encryption' => 'tls',
            'smtp_username'   => 'second@example.com',
            'smtp_password'   => 'secret',
        ]);

        $response->assertSessionHasErrors('plan');
        $this->assertSame(1, EmailAccount::where('tenant_id', $tenant->id)->count());
    }

    public function test_free_plan_blocks_team_member_invite(): void
    {
        $tenant = $this->tenant();
        $user   = $this->admin($tenant);

        $response = $this->actingAs($user)->post(route('team.store'), [
            'name'                  => 'New Member',
            'email'                 => 'member@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'member',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'member@example.com']);
    }

    public function test_max_users_overrides_plan_seat_limit_upward(): void
    {
        $tenant = $this->tenant('free', 'active', 5);

        $this->assertSame(5, app(PlanLimitsService::class)->limit($tenant, 'users'));
    }

    public function test_team_plan_allows_inviting_members(): void
    {
        $tenant = $this->tenant('enterprise');
        $user   = $this->admin($tenant);

        $response = $this->actingAs($user)->post(route('team.store'), [
            'name'                  => 'New Member',
            'email'                 => 'member@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'member',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'member@example.com', 'tenant_id' => $tenant->id]);
    }

    public function test_usage_summary_reports_all_metered_resources(): void
    {
        $tenant = $this->tenant();
        $user   = $this->admin($tenant);

        Contact::factory()->count(2)->create([
            'user_id'   => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        $summary = app(PlanLimitsService::class)->usageSummary($tenant);

        $this->assertSame(2, $summary['contacts']['used']);
        $this->assertSame(config('plans.plans.free.limits.contacts'), $summary['contacts']['limit']);
        $this->assertSame(1, $summary['users']['used']);
        $this->assertArrayHasKey('emails_per_day', $summary);
    }
}
