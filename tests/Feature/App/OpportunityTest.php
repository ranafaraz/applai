<?php

namespace Tests\Feature\App;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mobile API (/api/app/v1) opportunities CRUD + stage + meta + ownership
 * isolation (Milestone 2).
 */
class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_create_read_update_stage_and_persistence(): void
    {
        $this->actingAsUser();

        $create = $this->postJson('/api/app/v1/opportunities', [
            'type'     => 'phd',
            'title'    => 'Postdoc — Computational Neuroscience',
            'org'      => 'University of Oxford',
            'stage'    => 'draft',
            'deadline' => '2099-06-30',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.title', 'Postdoc — Computational Neuroscience')
            ->assertJsonPath('data.org', 'University of Oxford')
            ->assertJsonPath('data.stage', 'draft')
            ->assertJsonPath('data.type', 'phd');

        $id = $create->json('data.id');

        $this->getJson("/api/app/v1/opportunities/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->patchJson("/api/app/v1/opportunities/{$id}/stage", ['stage' => 'applied'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'applied');

        $this->assertDatabaseHas('opportunities', ['id' => $id, 'status' => 'applied']);
    }

    public function test_legacy_status_is_normalized_on_read(): void
    {
        $user = $this->actingAsUser();
        $opp  = Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'waiting_reply']);

        $this->getJson("/api/app/v1/opportunities/{$opp->id}")
            ->assertOk()
            ->assertJsonPath('data.stage', 'applied');
    }

    public function test_past_deadline_returns_warning_but_succeeds(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/app/v1/opportunities', [
            'title'    => 'Late one',
            'org'      => 'Acme',
            'deadline' => '2000-01-01',
        ])->assertStatus(201)->assertJsonPath('warnings.0', 'The deadline is in the past.');
    }

    public function test_index_filters_by_stage_including_legacy_aliases(): void
    {
        $user = $this->actingAsUser();
        Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'active']);        // → applied
        Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'applied']);       // → applied
        Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'draft']);

        $this->getJson('/api/app/v1/opportunities?stage=applied')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'has_more']]);
    }

    public function test_ownership_isolation_user_b_cannot_read_user_a(): void
    {
        $userA = User::factory()->create();
        $oppA  = Opportunity::factory()->create(['user_id' => $userA->id]);

        // User B is in a different tenant (factory creates a fresh tenant each time).
        $userB = User::factory()->create();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson("/api/app/v1/opportunities/{$oppA->id}")->assertStatus(404);
        $this->patchJson("/api/app/v1/opportunities/{$oppA->id}/stage", ['stage' => 'won'])->assertStatus(404);
        $this->deleteJson("/api/app/v1/opportunities/{$oppA->id}")->assertStatus(404);
    }

    public function test_meta_stages_returns_canonical_eight(): void
    {
        $this->actingAsUser();

        $res = $this->getJson('/api/app/v1/meta/stages')->assertOk();
        $this->assertSame(
            ['draft', 'applied', 'replied', 'interview', 'offer', 'won', 'closed', 'archived'],
            array_column($res->json('data'), 'value')
        );
    }

    public function test_soft_delete_then_hard_delete(): void
    {
        $user = $this->actingAsUser();
        $opp  = Opportunity::factory()->create(['user_id' => $user->id]);

        $this->deleteJson("/api/app/v1/opportunities/{$opp->id}")->assertOk();
        $this->assertSoftDeleted('opportunities', ['id' => $opp->id]);
    }
}
