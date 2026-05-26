<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_index_shows_scheduled_follow_up_email_messages(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);
        $opportunity = Opportunity::factory()->create(['user_id' => $user->id]);
        $contact = Contact::factory()->create(['user_id' => $user->id]);

        EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
            'subject' => 'Following up: CTO role',
            'to_email' => $contact->email,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(5),
            'is_follow_up' => true,
            'follow_up_number' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('follow-ups.index'))
            ->assertOk()
            ->assertSee('Scheduled follow-up emails')
            ->assertSee('Following up: CTO role')
            ->assertSee($contact->email);
    }

    public function test_opportunity_pending_follow_up_count_includes_scheduled_follow_up_emails(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);
        $opportunity = Opportunity::factory()->create(['user_id' => $user->id]);

        EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'opportunity_id' => $opportunity->id,
            'subject' => 'Following up: imported scheduled email',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(5),
            'is_follow_up' => true,
            'follow_up_number' => 1,
        ]);

        FollowUp::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'opportunity_id' => $opportunity->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('opportunities.show', $opportunity))
            ->assertOk()
            ->assertSee('>2</p><p class="text-slate-500 text-xs">Pending Follow-ups</p>', false)
            ->assertSee('Following up: imported scheduled email');
    }
}
