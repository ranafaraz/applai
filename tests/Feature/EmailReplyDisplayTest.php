<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\InboxAttachment;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailReplyDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_email_detail_shows_matched_inbox_replies(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id, 'email' => 'sender@example.com']);
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'recipient@example.com']);
        $opportunity = Opportunity::factory()->create(['user_id' => $user->id, 'title' => 'Production E2E Opportunity']);
        $email = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
            'subject' => 'CRM-E2E display test',
            'status' => 'sent',
        ]);

        InboxMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'matched_contact_id' => $contact->id,
            'matched_opportunity_id' => $opportunity->id,
            'matched_outbound_id' => $email->id,
            'from_email' => $contact->email,
            'body_text' => 'This reply should be visible on the sent email.',
        ]);

        $response = $this->actingAs($user)->get(route('emails.show', $email));

        $response
            ->assertOk()
            ->assertSee('Replies Received')
            ->assertSee('This reply should be visible on the sent email.')
            ->assertSee('recipient@example.com');
    }

    public function test_inbox_reply_detail_links_back_to_original_email(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);
        $contact = Contact::factory()->create(['user_id' => $user->id]);
        $opportunity = Opportunity::factory()->create(['user_id' => $user->id]);
        $email = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
            'subject' => 'Original E2E Outreach',
            'status' => 'sent',
        ]);
        $reply = InboxMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'matched_contact_id' => $contact->id,
            'matched_opportunity_id' => $opportunity->id,
            'matched_outbound_id' => $email->id,
        ]);

        $response = $this->actingAs($user)->get(route('inbox.show', $reply));

        $response
            ->assertOk()
            ->assertSee('Original email')
            ->assertSee('Original E2E Outreach');
    }

    public function test_tenant_scoped_inbox_reply_detail_is_visible(): void
    {
        $tenant = Tenant::create(['name' => 'Display Tenant', 'status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $account = EmailAccount::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $contact = Contact::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $opportunity = Opportunity::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $email = EmailMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
            'subject' => 'Tenant visible original',
            'status' => 'sent',
        ]);
        $reply = InboxMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'matched_contact_id' => $contact->id,
            'matched_opportunity_id' => $opportunity->id,
            'matched_outbound_id' => $email->id,
            'body_text' => 'Tenant visible reply',
        ]);

        $response = $this->actingAs($user)->get(route('inbox.show', $reply));

        $response
            ->assertOk()
            ->assertSee('Tenant visible reply')
            ->assertSee('Tenant visible original');
    }

    public function test_inbox_reply_detail_shows_and_downloads_received_attachments(): void
    {
        Storage::disk('local')->put('inbox-attachments/test/reply.txt', 'reply attachment');

        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);
        $reply = InboxMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'subject' => 'Reply with attachment',
        ]);
        $attachment = InboxAttachment::create([
            'inbox_message_id' => $reply->id,
            'file_name' => 'reply.txt',
            'file_path' => 'inbox-attachments/test/reply.txt',
            'mime_type' => 'text/plain',
            'file_size' => 16,
        ]);

        $this->actingAs($user)
            ->get(route('inbox.show', $reply))
            ->assertOk()
            ->assertSee('Attachments')
            ->assertSee('reply.txt');

        $this->actingAs($user)
            ->get(route('inbox.attachments.download', [$reply, $attachment]))
            ->assertOk()
            ->assertDownload('reply.txt');
    }
}
