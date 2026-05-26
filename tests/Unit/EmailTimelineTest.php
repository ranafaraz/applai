<?php

namespace Tests\Unit;

use App\Events\EmailSent;
use App\Listeners\LogEmailSentToTimeline;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Services\EmailSendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_sent_listener_is_idempotent_for_same_message(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);
        $opportunity = Opportunity::factory()->create(['user_id' => $user->id]);
        $message = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'opportunity_id' => $opportunity->id,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $listener = app(LogEmailSentToTimeline::class);
        $listener->handle(new EmailSent($message));
        $listener->handle(new EmailSent($message));

        $this->assertSame(1, TimelineEvent::where('timelineable_type', EmailMessage::class)->where('timelineable_id', $message->id)->count());
        $this->assertSame(1, TimelineEvent::where('timelineable_type', Opportunity::class)->where('timelineable_id', $opportunity->id)->count());
    }

    public function test_already_sent_message_is_not_sent_again(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'emails_sent_today' => 0,
        ]);
        $message = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $result = app(EmailSendingService::class)->sendEmail($message);

        $this->assertTrue($result);
        $this->assertSame(0, $account->fresh()->emails_sent_today);
        $this->assertSame('sent', $message->fresh()->status);
    }
}
