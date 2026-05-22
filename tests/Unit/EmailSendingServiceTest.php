<?php

namespace Tests\Unit;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\SuppressionList;
use App\Models\User;
use App\Services\EmailSendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailSendingServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailSendingService $service;
    private User $user;
    private EmailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmailSendingService::class);

        $this->user = User::factory()->create();

        $this->account = EmailAccount::factory()->create([
            'user_id'           => $this->user->id,
            'is_active'         => true,
            'daily_limit'       => 50,
            'hourly_limit'      => 10,
            'emails_sent_today' => 0,
        ]);
    }

    // =========================================================================
    // Suppression checking
    // =========================================================================

    public function test_can_send_from_active_account_within_limits(): void
    {
        $result = $this->service->canSendFromAccount($this->account);

        $this->assertTrue($result);
    }

    public function test_cannot_send_from_inactive_account(): void
    {
        $this->account->update(['is_active' => false]);

        $result = $this->service->canSendFromAccount($this->account->fresh());

        $this->assertFalse($result);
    }

    public function test_cannot_send_when_daily_limit_is_reached(): void
    {
        $this->account->update([
            'daily_limit'       => 10,
            'emails_sent_today' => 10,
        ]);

        $result = $this->service->canSendFromAccount($this->account->fresh());

        $this->assertFalse($result);
    }

    public function test_can_send_when_daily_limit_is_not_reached(): void
    {
        $this->account->update([
            'daily_limit'       => 50,
            'emails_sent_today' => 49,
        ]);

        $result = $this->service->canSendFromAccount($this->account->fresh());

        $this->assertTrue($result);
    }

    public function test_can_send_when_daily_limit_is_zero_unlimited(): void
    {
        $this->account->update([
            'daily_limit'       => 0,
            'emails_sent_today' => 9999,
        ]);

        $result = $this->service->canSendFromAccount($this->account->fresh());

        $this->assertTrue($result);
    }

    public function test_cannot_send_when_hourly_limit_is_reached(): void
    {
        $this->account->update([
            'hourly_limit' => 2,
        ]);

        // Create 2 "sent" messages within the last hour
        EmailMessage::factory()->count(2)->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $this->account->id,
            'direction'        => 'outbound',
            'status'           => 'sent',
            'sent_at'          => now()->subMinutes(30),
        ]);

        $result = $this->service->canSendFromAccount($this->account->fresh());

        $this->assertFalse($result);
    }

    public function test_can_send_when_hourly_messages_are_outside_window(): void
    {
        $this->account->update([
            'hourly_limit' => 2,
        ]);

        // Create sent messages older than 1 hour (outside the window)
        EmailMessage::factory()->count(3)->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $this->account->id,
            'direction'        => 'outbound',
            'status'           => 'sent',
            'sent_at'          => now()->subHours(2),
        ]);

        $result = $this->service->canSendFromAccount($this->account->fresh());

        $this->assertTrue($result);
    }

    // =========================================================================
    // Suppression list integration
    // =========================================================================

    public function test_sending_to_suppressed_email_marks_message_as_failed(): void
    {
        // Add recipient to suppression list
        SuppressionList::factory()->create([
            'user_id' => $this->user->id,
            'email'   => 'suppressed@example.com',
        ]);

        $message = EmailMessage::factory()->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $this->account->id,
            'to_email'         => 'suppressed@example.com',
            'status'           => 'scheduled',
        ]);

        $result = $this->service->sendEmail($message);

        $this->assertFalse($result);

        $message->refresh();
        $this->assertEquals('failed', $message->status);
        $this->assertNotNull($message->failure_reason);
    }

    public function test_sending_to_suppressed_email_with_different_case(): void
    {
        SuppressionList::factory()->create([
            'user_id' => $this->user->id,
            'email'   => 'suppressed@example.com',
        ]);

        $message = EmailMessage::factory()->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $this->account->id,
            'to_email'         => 'SUPPRESSED@EXAMPLE.COM',
            'status'           => 'scheduled',
        ]);

        $result = $this->service->sendEmail($message);

        $this->assertFalse($result);
    }

    public function test_sending_when_account_at_daily_limit_marks_message_failed(): void
    {
        $this->account->update([
            'daily_limit'       => 5,
            'emails_sent_today' => 5,
        ]);

        $message = EmailMessage::factory()->create([
            'user_id'          => $this->user->id,
            'email_account_id' => $this->account->id,
            'to_email'         => 'valid@example.com',
            'status'           => 'scheduled',
        ]);

        $result = $this->service->sendEmail($message);

        $this->assertFalse($result);

        $message->refresh();
        $this->assertEquals('failed', $message->status);
    }

    // =========================================================================
    // SuppressionList model helper
    // =========================================================================

    public function test_suppression_list_is_suppressed_returns_true_for_suppressed_email(): void
    {
        SuppressionList::factory()->create([
            'user_id' => $this->user->id,
            'email'   => 'blocked@example.com',
        ]);

        $this->assertTrue(
            SuppressionList::isSuppressed($this->user->id, 'blocked@example.com')
        );
    }

    public function test_suppression_list_is_suppressed_returns_false_for_clean_email(): void
    {
        $this->assertFalse(
            SuppressionList::isSuppressed($this->user->id, 'clean@example.com')
        );
    }

    public function test_suppression_check_is_user_scoped(): void
    {
        $other = User::factory()->create();

        SuppressionList::factory()->create([
            'user_id' => $other->id,
            'email'   => 'blocked@example.com',
        ]);

        // Should NOT be suppressed for our user
        $this->assertFalse(
            SuppressionList::isSuppressed($this->user->id, 'blocked@example.com')
        );
    }
}
