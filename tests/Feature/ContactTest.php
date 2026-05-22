<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
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

    public function test_contacts_index_requires_authentication(): void
    {
        $this->get(route('contacts.index'))->assertRedirect(route('login'));
    }

    public function test_contacts_index_returns_ok_for_authenticated_user(): void
    {
        $this->actingAs($this->user)->get(route('contacts.index'))->assertOk();
    }

    public function test_contacts_index_only_shows_own_contacts(): void
    {
        $other = User::factory()->create();
        Contact::factory()->create(['user_id' => $this->user->id, 'first_name' => 'MyContact']);
        Contact::factory()->create(['user_id' => $other->id, 'first_name' => 'OtherContact']);

        $response = $this->actingAs($this->user)->get(route('contacts.index'));

        $response->assertSee('MyContact');
        $response->assertDontSee('OtherContact');
    }

    // =========================================================================
    // Create / Store
    // =========================================================================

    public function test_contact_create_page_is_accessible(): void
    {
        $this->actingAs($this->user)->get(route('contacts.create'))->assertOk();
    }

    public function test_user_can_create_a_contact(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'first_name' => 'Alice',
            'last_name'  => 'Smith',
            'email'      => 'alice@example.com',
            'company'    => 'Acme Corp',
            'job_title'  => 'Engineer',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', [
            'user_id'    => $this->user->id,
            'first_name' => 'Alice',
            'email'      => 'alice@example.com',
        ]);
    }

    public function test_store_contact_requires_first_name(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'email' => 'alice@example.com',
        ]);

        $response->assertSessionHasErrors('first_name');
    }

    public function test_store_contact_requires_valid_email(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'first_name' => 'Alice',
            'email'      => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function test_user_can_view_own_contact(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get(route('contacts.show', $contact))->assertOk();
    }

    public function test_user_cannot_view_another_users_contact(): void
    {
        $other   = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get(route('contacts.show', $contact))->assertNotFound();
    }

    // =========================================================================
    // Edit / Update
    // =========================================================================

    public function test_user_can_edit_own_contact(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get(route('contacts.edit', $contact))->assertOk();
    }

    public function test_user_can_update_own_contact(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->put(route('contacts.update', $contact), [
            'first_name' => 'Updated',
            'email'      => $contact->email,
        ]);

        $response->assertRedirect(route('contacts.show', $contact));
        $this->assertDatabaseHas('contacts', [
            'id'         => $contact->id,
            'first_name' => 'Updated',
        ]);
    }

    public function test_user_cannot_update_another_users_contact(): void
    {
        $other   = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->put(route('contacts.update', $contact), [
                'first_name' => 'Hacked',
                'email'      => $contact->email,
            ])
            ->assertNotFound();
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    public function test_user_can_delete_own_contact(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('contacts.destroy', $contact));

        $response->assertRedirect(route('contacts.index'));
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_user_cannot_delete_another_users_contact(): void
    {
        $other   = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->delete(route('contacts.destroy', $contact))
            ->assertNotFound();
    }

    // =========================================================================
    // Suppression
    // =========================================================================

    public function test_user_can_suppress_a_contact(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('contacts.suppress', $contact));

        $response->assertRedirect(route('contacts.show', $contact));

        $contact->refresh();
        $this->assertEquals('suppressed', $contact->status);

        $this->assertDatabaseHas('suppression_list', [
            'user_id' => $this->user->id,
            'email'   => strtolower($contact->email),
        ]);
    }

    public function test_suppressing_a_contact_a_second_time_does_not_create_duplicate(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'active',
        ]);

        $this->actingAs($this->user)->post(route('contacts.suppress', $contact));
        $this->actingAs($this->user)->post(route('contacts.suppress', $contact));

        $this->assertDatabaseCount('suppression_list', 1);
    }
}
