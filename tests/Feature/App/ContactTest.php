<?php

namespace Tests\Feature\App;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mobile API (/api/app/v1) contacts CRUD + suppress/unsuppress + links +
 * ownership isolation (Milestone 3).
 */
class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_create_and_read_contact(): void
    {
        $this->actingAsUser();

        $create = $this->postJson('/api/app/v1/contacts', [
            'full_name' => 'Dr. Rachel Singh',
            'email'     => 'rachel@oxford.ac.uk',
            'role'      => 'Professor',
            'org'       => 'University of Oxford',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.full_name', 'Dr. Rachel Singh')
            ->assertJsonPath('data.email', 'rachel@oxford.ac.uk')
            ->assertJsonPath('data.role', 'Professor')
            ->assertJsonPath('data.org', 'University of Oxford')
            ->assertJsonPath('data.status', 'active');

        $id = $create->json('data.id');

        $this->getJson("/api/app/v1/contacts/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonStructure(['data' => ['initials', 'avatar_color', 'notes', 'last_contacted_at']]);
    }

    public function test_initials_and_avatar_color_are_computed(): void
    {
        $this->actingAsUser();

        $res = $this->postJson('/api/app/v1/contacts', [
            'full_name' => 'Jane Doe',
            'email'     => 'jane@example.com',
        ])->assertStatus(201);

        $this->assertSame('JD', $res->json('data.initials'));
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $res->json('data.avatar_color'));
    }

    public function test_list_contacts_with_search(): void
    {
        $user = $this->actingAsUser();

        Contact::factory()->create(['user_id' => $user->id, 'first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@x.com', 'status' => 'active']);
        Contact::factory()->create(['user_id' => $user->id, 'first_name' => 'Bob',   'last_name' => 'Jones', 'email' => 'bob@x.com',   'status' => 'active']);

        $this->getJson('/api/app/v1/contacts?q=Alice')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Alice Smith');
    }

    public function test_list_suppressed_filter(): void
    {
        $user = $this->actingAsUser();

        Contact::factory()->create(['user_id' => $user->id, 'email' => 'a@x.com', 'status' => 'active']);
        Contact::factory()->create(['user_id' => $user->id, 'email' => 'b@x.com', 'status' => 'suppressed']);
        Contact::factory()->create(['user_id' => $user->id, 'email' => 'c@x.com', 'status' => 'bounced']);

        $this->getJson('/api/app/v1/contacts?suppressed=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/app/v1/contacts?suppressed=false')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_update_contact(): void
    {
        $user    = $this->actingAsUser();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'u@x.com', 'status' => 'active']);

        $this->patchJson("/api/app/v1/contacts/{$contact->id}", [
            'full_name' => 'Updated Name',
            'org'       => 'New Corp',
        ])->assertOk()
          ->assertJsonPath('data.full_name', 'Updated Name')
          ->assertJsonPath('data.org', 'New Corp');
    }

    public function test_delete_soft_deletes_contact(): void
    {
        $user    = $this->actingAsUser();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'd@x.com', 'status' => 'active']);

        $this->deleteJson("/api/app/v1/contacts/{$contact->id}")->assertOk();

        $this->getJson("/api/app/v1/contacts/{$contact->id}")->assertStatus(404);

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_suppress_sets_status_suppressed(): void
    {
        $user    = $this->actingAsUser();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 's@x.com', 'status' => 'active']);

        $this->postJson("/api/app/v1/contacts/{$contact->id}/suppress", ['reason' => 'not_relevant'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suppressed');

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'status' => 'suppressed']);
    }

    public function test_suppress_bounced_reason_sets_bounced_status(): void
    {
        $user    = $this->actingAsUser();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'b@x.com', 'status' => 'active']);

        $this->postJson("/api/app/v1/contacts/{$contact->id}/suppress", ['reason' => 'bounced'])
            ->assertOk()
            ->assertJsonPath('data.status', 'bounced');
    }

    public function test_unsuppress_restores_active(): void
    {
        $user    = $this->actingAsUser();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'u@x.com', 'status' => 'suppressed']);

        $this->deleteJson("/api/app/v1/contacts/{$contact->id}/suppress")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'status' => 'active']);
    }

    public function test_linked_opportunities_endpoint(): void
    {
        $user    = $this->actingAsUser();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'o@x.com', 'status' => 'active']);
        $opp     = Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'applied']);
        $contact->opportunities()->attach($opp->id, ['role' => 'recruiter']);

        $res = $this->getJson("/api/app/v1/contacts/{$contact->id}/opportunities")
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame($opp->id, $res->json('data.0.id'));
        $this->assertSame('recruiter', $res->json('data.0.role'));
    }

    public function test_emails_endpoint_returns_list(): void
    {
        $user    = $this->actingAsUser();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'e@x.com', 'status' => 'active']);

        $this->getJson("/api/app/v1/contacts/{$contact->id}/emails")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_ownership_isolation_user_b_cannot_read_user_a_contact(): void
    {
        $userA   = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $userA->id, 'email' => 'a@x.com', 'status' => 'active']);

        $userB = User::factory()->create();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson("/api/app/v1/contacts/{$contact->id}")->assertStatus(404);
    }

    public function test_ownership_isolation_user_b_cannot_suppress_user_a_contact(): void
    {
        $userA   = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $userA->id, 'email' => 'a@x.com', 'status' => 'active']);

        $userB = User::factory()->create();
        Sanctum::actingAs($userB, ['*']);

        $this->postJson("/api/app/v1/contacts/{$contact->id}/suppress", ['reason' => 'not_relevant'])
            ->assertStatus(404);
    }

    public function test_validation_requires_full_name_and_email_on_create(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/app/v1/contacts', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['full_name', 'email']]);
    }

    public function test_not_found_returns_error_envelope(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/app/v1/contacts/99999')
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }
}
