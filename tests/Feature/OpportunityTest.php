<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\TimelineEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityTest extends TestCase
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

    public function test_opportunities_index_requires_authentication(): void
    {
        $this->get(route('opportunities.index'))->assertRedirect(route('login'));
    }

    public function test_opportunities_index_returns_ok(): void
    {
        $this->actingAs($this->user)->get(route('opportunities.index'))->assertOk();
    }

    public function test_opportunities_index_only_shows_own_opportunities(): void
    {
        $other = User::factory()->create();
        Opportunity::factory()->create(['user_id' => $this->user->id, 'title' => 'My Opportunity']);
        Opportunity::factory()->create(['user_id' => $other->id, 'title' => 'Their Opportunity']);

        $response = $this->actingAs($this->user)->get(route('opportunities.index'));

        $response->assertSee('My Opportunity');
        $response->assertDontSee('Their Opportunity');
    }

    // =========================================================================
    // Create / Store
    // =========================================================================

    public function test_opportunity_create_page_is_accessible(): void
    {
        $this->actingAs($this->user)->get(route('opportunities.create'))->assertOk();
    }

    public function test_user_can_create_an_opportunity(): void
    {
        $response = $this->actingAs($this->user)->post(route('opportunities.store'), [
            'title'        => 'Software Engineer at Acme',
            'type'         => 'job',
            'organization' => 'Acme Corp',
            'status'       => 'active',
            'priority'     => 'high',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opportunities', [
            'user_id' => $this->user->id,
            'title'   => 'Software Engineer at Acme',
            'type'    => 'job',
        ]);
    }

    public function test_store_opportunity_requires_title(): void
    {
        $response = $this->actingAs($this->user)->post(route('opportunities.store'), [
            'type' => 'job',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_store_opportunity_validates_priority_enum(): void
    {
        $response = $this->actingAs($this->user)->post(route('opportunities.store'), [
            'title'    => 'Test',
            'priority' => 'invalid-priority',
        ]);

        $response->assertSessionHasErrors('priority');
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function test_user_can_view_own_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get(route('opportunities.show', $opportunity))->assertOk();
    }

    public function test_user_cannot_view_another_users_opportunity(): void
    {
        $other       = User::factory()->create();
        $opportunity = Opportunity::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->get(route('opportunities.show', $opportunity))
            ->assertNotFound();
    }

    // =========================================================================
    // Edit / Update
    // =========================================================================

    public function test_user_can_update_own_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->put(route('opportunities.update', $opportunity), [
            'title'    => 'Updated Title',
            'priority' => 'medium',
        ]);

        $response->assertRedirect(route('opportunities.show', $opportunity));
        $this->assertDatabaseHas('opportunities', [
            'id'    => $opportunity->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_user_cannot_update_another_users_opportunity(): void
    {
        $other       = User::factory()->create();
        $opportunity = Opportunity::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->put(route('opportunities.update', $opportunity), ['title' => 'Hacked'])
            ->assertNotFound();
    }

    // =========================================================================
    // Status update
    // =========================================================================

    public function test_user_can_update_opportunity_status(): void
    {
        $opportunity = Opportunity::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('opportunities.update-status', $opportunity), [
                'status' => 'waiting_reply',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opportunities', [
            'id'     => $opportunity->id,
            'status' => 'waiting_reply',
        ]);
    }

    public function test_status_update_requires_status_field(): void
    {
        $opportunity = Opportunity::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->patch(route('opportunities.update-status', $opportunity), []);

        $response->assertSessionHasErrors('status');
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    public function test_user_can_delete_own_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('opportunities.destroy', $opportunity));

        $response->assertRedirect(route('opportunities.index'));
        $this->assertSoftDeleted('opportunities', ['id' => $opportunity->id]);
    }

    public function test_user_cannot_delete_another_users_opportunity(): void
    {
        $other       = User::factory()->create();
        $opportunity = Opportunity::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->delete(route('opportunities.destroy', $opportunity))
            ->assertNotFound();
    }

    // =========================================================================
    // last_activity_at timestamp
    // =========================================================================

    public function test_updating_opportunity_touches_last_activity_at(): void
    {
        $opportunity = Opportunity::factory()->create([
            'user_id'          => $this->user->id,
            'last_activity_at' => now()->subDays(10),
        ]);

        $this->actingAs($this->user)->put(route('opportunities.update', $opportunity), [
            'title'    => 'Fresh Title',
            'priority' => 'high',
        ]);

        $opportunity->refresh();
        $this->assertTrue($opportunity->last_activity_at->greaterThan(now()->subMinute()));
    }
}
