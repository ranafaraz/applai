<?php

namespace Tests\Feature\App;

use App\Models\Contact;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mobile API (/api/app/v1) analytics endpoints (Milestone 6).
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ── Overview ─────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_overview(): void
    {
        $this->getJson('/api/app/v1/analytics/overview')->assertUnauthorized();
    }

    public function test_overview_returns_expected_structure(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/app/v1/analytics/overview')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'opportunities' => ['total', 'active', 'response_rate', 'win_rate'],
                'contacts'      => ['total', 'active'],
                'follow_ups'    => ['pending', 'overdue'],
            ]]);
    }

    public function test_overview_returns_zeros_with_no_data(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/app/v1/analytics/overview')->assertOk();

        $this->assertEquals(0, $response->json('data.opportunities.total'));
        $this->assertEquals(0.0, $response->json('data.opportunities.response_rate'));
        $this->assertEquals(0.0, $response->json('data.opportunities.win_rate'));
    }

    public function test_overview_counts_opportunities(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'applied']);
        Opportunity::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'replied']);

        $response = $this->getJson('/api/app/v1/analytics/overview')->assertOk();

        $this->assertEquals(5, $response->json('data.opportunities.total'));
        // active = applied + replied (not closed/archived/draft)
        $this->assertEquals(5, $response->json('data.opportunities.active'));
    }

    public function test_overview_response_rate_calculation(): void
    {
        $user = $this->actingAsUser();
        // 4 applied, 2 replied → responded=2, progressed=6, rate=2/6≈0.3333
        Opportunity::factory()->count(4)->create(['user_id' => $user->id, 'status' => 'applied']);
        Opportunity::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'replied']);

        $response = $this->getJson('/api/app/v1/analytics/overview')->assertOk();

        $rate = $response->json('data.opportunities.response_rate');
        $this->assertEqualsWithDelta(round(2 / 6, 4), $rate, 0.001);
    }

    public function test_overview_win_rate_calculation(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->count(8)->create(['user_id' => $user->id, 'status' => 'closed']);
        Opportunity::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'won']);

        $response = $this->getJson('/api/app/v1/analytics/overview')->assertOk();

        // win_rate = 2 / (10 non-archived) = 0.2
        $this->assertEqualsWithDelta(0.2, $response->json('data.opportunities.win_rate'), 0.001);
    }

    public function test_overview_counts_contacts(): void
    {
        $user = $this->actingAsUser();
        Contact::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'active']);
        Contact::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'suppressed']);

        $response = $this->getJson('/api/app/v1/analytics/overview')->assertOk();

        $this->assertEquals(4, $response->json('data.contacts.total'));
        $this->assertEquals(3, $response->json('data.contacts.active'));
    }

    public function test_overview_counts_pending_followups(): void
    {
        $user = $this->actingAsUser();
        $opp  = Opportunity::factory()->create(['user_id' => $user->id]);

        FollowUp::factory()->create([
            'user_id'          => $user->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
            'status'           => 'pending',
            'due_at'           => now()->addDay(),
        ]);
        FollowUp::factory()->create([
            'user_id'          => $user->id,
            'opportunity_id'   => $opp->id,
            'email_account_id' => null,
            'status'           => 'pending',
            'due_at'           => now()->subDay(),
        ]);

        $response = $this->getJson('/api/app/v1/analytics/overview')->assertOk();

        $this->assertEquals(2, $response->json('data.follow_ups.pending'));
        $this->assertEquals(1, $response->json('data.follow_ups.overdue'));
    }

    public function test_overview_excludes_other_users_data(): void
    {
        $this->actingAsUser();

        $other = User::factory()->create();
        Opportunity::factory()->count(5)->create(['user_id' => $other->id]);
        Contact::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/app/v1/analytics/overview')->assertOk();

        $this->assertEquals(0, $response->json('data.opportunities.total'));
        $this->assertEquals(0, $response->json('data.contacts.total'));
    }

    // ── Pipeline ─────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_pipeline(): void
    {
        $this->getJson('/api/app/v1/analytics/pipeline')->assertUnauthorized();
    }

    public function test_pipeline_returns_all_stages_in_order(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/app/v1/analytics/pipeline')->assertOk();

        $stages = collect($response->json('data.stages'))->pluck('stage')->toArray();
        $this->assertEquals(
            ['draft', 'applied', 'replied', 'interview', 'offer', 'won', 'closed', 'archived'],
            $stages
        );
    }

    public function test_pipeline_counts_by_stage(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'applied']);
        Opportunity::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'replied']);

        $response = $this->getJson('/api/app/v1/analytics/pipeline')->assertOk();

        $this->assertEquals(4, $response->json('data.total'));

        $byStage = collect($response->json('data.stages'))->keyBy('stage');
        $this->assertEquals(3, $byStage['applied']['count']);
        $this->assertEquals(1, $byStage['replied']['count']);
        $this->assertEquals(0, $byStage['draft']['count']);
    }

    public function test_pipeline_normalizes_legacy_statuses(): void
    {
        $user = $this->actingAsUser();
        // legacy 'active' and 'waiting_reply' → canonical 'applied'
        Opportunity::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'active']);
        Opportunity::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'waiting_reply']);

        $response = $this->getJson('/api/app/v1/analytics/pipeline')->assertOk();

        $byStage = collect($response->json('data.stages'))->keyBy('stage');
        $this->assertEquals(3, $byStage['applied']['count']);
    }

    public function test_pipeline_pct_sums_to_100(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'applied']);
        Opportunity::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'won']);

        $response = $this->getJson('/api/app/v1/analytics/pipeline')->assertOk();

        $totalPct = collect($response->json('data.stages'))->sum('pct');
        $this->assertEqualsWithDelta(100.0, $totalPct, 0.5);
    }

    public function test_pipeline_first_stage_has_null_conversion(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'draft']);

        $response = $this->getJson('/api/app/v1/analytics/pipeline')->assertOk();

        $first = $response->json('data.stages.0');
        $this->assertEquals('draft', $first['stage']);
        $this->assertNull($first['conversion']);
    }

    // ── Activity ─────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_activity(): void
    {
        $this->getJson('/api/app/v1/analytics/activity')->assertUnauthorized();
    }

    public function test_activity_defaults_to_30_days(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/app/v1/analytics/activity')->assertOk();

        $this->assertEquals(30, $response->json('data.period_days'));
        $this->assertCount(30, $response->json('data.applications'));
        $this->assertCount(30, $response->json('data.emails_sent'));
    }

    public function test_activity_accepts_7d_period(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/app/v1/analytics/activity?period=7d')->assertOk();

        $this->assertEquals(7, $response->json('data.period_days'));
        $this->assertCount(7, $response->json('data.applications'));
    }

    public function test_activity_accepts_90d_period(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/app/v1/analytics/activity?period=90d')->assertOk();

        $this->assertEquals(90, $response->json('data.period_days'));
        $this->assertCount(90, $response->json('data.applications'));
    }

    public function test_activity_counts_todays_applications(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/app/v1/analytics/activity?period=7d')->assertOk();

        $today = now()->toDateString();
        $entry = collect($response->json('data.applications'))
            ->firstWhere('date', $today);

        $this->assertNotNull($entry);
        $this->assertEquals(2, $entry['count']);
    }

    public function test_activity_series_has_date_and_count_keys(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/app/v1/analytics/activity?period=7d')->assertOk();

        $first = $response->json('data.applications.0');
        $this->assertArrayHasKey('date', $first);
        $this->assertArrayHasKey('count', $first);
    }

    public function test_activity_excludes_other_users_applications(): void
    {
        $this->actingAsUser();
        $other = User::factory()->create();
        Opportunity::factory()->count(5)->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/app/v1/analytics/activity?period=7d')->assertOk();

        $totalCount = collect($response->json('data.applications'))->sum('count');
        $this->assertEquals(0, $totalCount);
    }
}
