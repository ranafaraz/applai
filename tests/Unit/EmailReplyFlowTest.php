<?php

namespace Tests\Unit;

use App\Events\ReplyReceived;
use App\Listeners\HandleReplyReceived;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Services\EmailSendingService;
use App\Services\FollowUpService;
use App\Services\ImapSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailReplyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_id_headers_are_normalized_and_extracted(): void
    {
        $this->assertSame('<abc@example.com>', EmailSendingService::normalizeMessageId('  <ABC@example.com>,  '));
        $this->assertNull(EmailSendingService::normalizeMessageId('not-a-message-id'));

        $this->assertSame(
            ['<first@example.com>', '<second@example.com>'],
            EmailSendingService::extractMessageIds(' <FIRST@example.com> <second@example.com> ')
        );
    }

    public function test_replies_match_outbound_by_in_reply_to_and_references_headers(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'reply@example.com']);
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);

        $outbound = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'message_id' => '<outbound@example.com>',
            'subject' => 'CRM E2E thread',
            'status' => 'sent',
            'direction' => 'outbound',
        ]);

        $service = app(ImapSyncService::class);
        $method = (new ReflectionClass($service))->getMethod('matchOutbound');
        $method->setAccessible(true);

        $byReplyTo = $method->invoke($service, $user->id, ' OUTBOUND@example.com ', null, 'Re: CRM E2E thread', $contact);
        $byReferences = $method->invoke($service, $user->id, null, '<older@example.com> <OUTBOUND@example.com>', 'Re: CRM E2E thread', $contact);

        $this->assertTrue($outbound->is($byReplyTo));
        $this->assertTrue($outbound->is($byReferences));
    }

    public function test_gmail_sync_scans_all_mail_and_spam_folders(): void
    {
        $account = EmailAccount::factory()->create(['imap_host' => 'imap.gmail.com']);

        $service = app(ImapSyncService::class);
        $method = (new ReflectionClass($service))->getMethod('syncFolderNames');
        $method->setAccessible(true);

        $this->assertSame(
            ['INBOX', '[Gmail]/All Mail', '[Gmail]/Spam', '[Gmail]/Junk'],
            $method->invoke($service, $account)
        );
    }

    public function test_reply_listener_is_idempotent_for_timeline_and_cancels_followups(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);
        $contact = Contact::factory()->create(['user_id' => $user->id]);
        $opportunity = Opportunity::factory()->waitingReply()->create(['user_id' => $user->id]);
        $outbound = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
            'status' => 'sent',
        ]);

        $followUp = FollowUp::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
            'email_message_id' => $outbound->id,
            'status' => 'pending',
        ]);

        $reply = InboxMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'matched_contact_id' => $contact->id,
            'matched_opportunity_id' => $opportunity->id,
            'matched_outbound_id' => $outbound->id,
        ]);

        $listener = app(HandleReplyReceived::class);
        $listener->handle(new ReplyReceived($reply));
        $listener->handle(new ReplyReceived($reply));

        $this->assertSame('cancelled', $followUp->fresh()->status);
        $this->assertSame('reply_received', $followUp->fresh()->cancel_reason);
        $this->assertSame('replied', $opportunity->fresh()->status);
        $this->assertSame(1, TimelineEvent::where('timelineable_type', Opportunity::class)->where('timelineable_id', $opportunity->id)->where('event_type', 'reply_received')->count());
        $this->assertSame(1, TimelineEvent::where('timelineable_type', Contact::class)->where('timelineable_id', $contact->id)->where('event_type', 'reply_received')->count());
    }

    public function test_reply_cancellation_uses_exact_outbound_when_contact_match_is_ambiguous(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);
        $outboundContact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'shared@example.com']);
        $otherContact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'shared@example.com']);
        $opportunity = Opportunity::factory()->create(['user_id' => $user->id]);
        $outbound = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $outboundContact->id,
            'opportunity_id' => $opportunity->id,
            'status' => 'sent',
        ]);

        $followUp = FollowUp::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $outboundContact->id,
            'opportunity_id' => $opportunity->id,
            'email_message_id' => $outbound->id,
            'status' => 'pending',
        ]);

        $reply = InboxMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'matched_contact_id' => $otherContact->id,
            'matched_opportunity_id' => $opportunity->id,
            'matched_outbound_id' => $outbound->id,
        ]);

        app(ImapSyncService::class)->cancelFollowUpsOnReply($reply);

        $this->assertSame('cancelled', $followUp->fresh()->status);
        $this->assertSame('reply_received', $followUp->fresh()->cancel_reason);
    }

    public function test_follow_up_email_preserves_tenant_and_copies_api_attachments(): void
    {
        $tenantId = Tenant::create(['name' => 'E2E Tenant', 'status' => 'active'])->id;
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $account = EmailAccount::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id]);
        $contact = Contact::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id]);
        $opportunity = Opportunity::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id]);
        $outbound = EmailMessage::factory()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
        ]);

        $followUp = FollowUp::factory()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
            'email_message_id' => $outbound->id,
            'subject' => 'Re: Outreach',
            'body' => '<p>Follow up</p>',
        ]);

        $apiAttachment = \App\Models\ApiAttachment::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'filename' => 'test.txt',
            'public_url' => 'https://example.com/test.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 12,
            'category' => 'other',
        ]);
        $followUp->apiAttachments()->sync([$apiAttachment->id]);

        $service = app(FollowUpService::class);
        $method = (new ReflectionClass($service))->getMethod('buildFollowUpEmailMessage');
        $method->setAccessible(true);

        /** @var EmailMessage $emailMessage */
        $emailMessage = $method->invoke($service, $followUp->fresh());

        $this->assertSame($tenantId, $emailMessage->tenant_id);
        $this->assertSame($user->id, $emailMessage->user_id);
        $this->assertSame($account->id, $emailMessage->email_account_id);
        $this->assertSame($contact->email, $emailMessage->to_email);
        $this->assertTrue($emailMessage->apiAttachments()->whereKey($apiAttachment->id)->exists());
    }

    public function test_local_email_attachments_are_added_to_mime_message(): void
    {
        Storage::disk('local')->put('email-attachments/e2e.txt', 'attachment body');

        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);
        $emailMessage = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
        ]);

        EmailAttachment::create([
            'email_message_id' => $emailMessage->id,
            'file_name' => 'e2e.txt',
            'file_path' => 'email-attachments/e2e.txt',
            'mime_type' => 'text/plain',
            'file_size' => 15,
        ]);

        $emailMessage->load('attachments', 'apiAttachments');
        $mime = new Email();

        $service = app(EmailSendingService::class);
        $method = (new ReflectionClass($service))->getMethod('attachFiles');
        $method->setAccessible(true);
        $method->invoke($service, $mime, $emailMessage);

        $this->assertCount(1, $mime->getAttachments());
    }

    public function test_daily_counter_reset_only_resets_accounts_due_for_reset(): void
    {
        $dueAccount = EmailAccount::factory()->create([
            'emails_sent_today' => 10,
            'last_reset_at' => now()->subDay(),
        ]);
        $freshAccount = EmailAccount::factory()->create([
            'emails_sent_today' => 5,
            'last_reset_at' => now(),
        ]);

        app(EmailSendingService::class)->resetDailyCounters();

        $this->assertSame(0, $dueAccount->fresh()->emails_sent_today);
        $this->assertSame(5, $freshAccount->fresh()->emails_sent_today);
    }
}
