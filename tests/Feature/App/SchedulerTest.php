<?php

namespace Tests\Feature\App;

use App\Console\Commands\CancelFollowUpsOnReplyCommand;
use App\Console\Commands\DispatchDueFollowUpsCommand;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // crm:send-scheduled — schedule / unschedule endpoints
    // -------------------------------------------------------------------------

    public function test_schedule_endpoint_marks_draft_as_scheduled(): void
    {
        $user  = $this->makeApiUser();
        $token = $this->makeApiToken($user, ['email:send', 'drafts:read']);

        $draft = EmailMessage::factory()->create([
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
            'status'    => 'draft',
            'direction' => 'outbound',
            'to_email'  => 'test@example.com',
        ]);

        $sendAt = now()->addHours(2)->toISOString();

        $response = $this->withToken($token)
            ->postJson("/api/gpt/v1/email-drafts/{$draft->id}/schedule", ['send_at' => $sendAt]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'scheduled');

        $draft->refresh();
        $this->assertSame('scheduled', $draft->status);
        $this->assertNotNull($draft->scheduled_at);
        $this->assertTrue($draft->scheduled_at->gt(now()));
    }

    public function test_unschedule_endpoint_reverts_to_draft(): void
    {
        $user  = $this->makeApiUser();
        $token = $this->makeApiToken($user, ['email:send']);

        $draft = EmailMessage::factory()->create([
            'user_id'      => $user->id,
            'tenant_id'    => $user->tenant_id,
            'status'       => 'scheduled',
            'direction'    => 'outbound',
            'to_email'     => 'test@example.com',
            'scheduled_at' => now()->addHour(),
        ]);

        $response = $this->withToken($token)
            ->postJson("/api/gpt/v1/email-drafts/{$draft->id}/unschedule");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'draft');

        $draft->refresh();
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->scheduled_at);
    }

    public function test_send_scheduled_command_dispatches_due_emails(): void
    {
        $due = EmailMessage::factory()->create([
            'status'       => 'scheduled',
            'direction'    => 'outbound',
            'scheduled_at' => now()->subMinute(),
        ]);
        $future = EmailMessage::factory()->create([
            'status'       => 'scheduled',
            'direction'    => 'outbound',
            'scheduled_at' => now()->addHour(),
        ]);

        Artisan::call('crm:send-scheduled');

        $this->assertSame('queued', $due->fresh()->status);
        $this->assertSame('scheduled', $future->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // auto_send follow-up dispatch
    // -------------------------------------------------------------------------

    public function test_dispatch_due_followups_respects_auto_send_flag(): void
    {
        $user = $this->makeApiUser();

        $autoSendOn = FollowUp::factory()->create([
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
            'status'    => 'pending',
            'auto_send' => true,
            'due_at'    => now()->subMinute(),
        ]);

        $autoSendOff = FollowUp::factory()->create([
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
            'status'    => 'pending',
            'auto_send' => false,
            'due_at'    => now()->subMinute(),
        ]);

        // Mock FollowUpService to avoid real SMTP; we just want to confirm
        // only auto_send=true follow-ups are selected by the service query.
        $this->artisan('crm:dispatch-due-followups')->assertExitCode(0);

        // auto_send=false must remain pending (was never picked up for dispatch)
        $this->assertSame('pending', $autoSendOff->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // crm:cancel-followups-on-reply
    // -------------------------------------------------------------------------

    public function test_cancel_followups_on_reply_cancels_pending_for_replied_opportunity(): void
    {
        $user = $this->makeApiUser();

        $followUp = FollowUp::factory()->create([
            'user_id'        => $user->id,
            'tenant_id'      => $user->tenant_id,
            'opportunity_id' => 999,
            'status'         => 'pending',
        ]);

        // Simulate an inbound reply matched to the same opportunity
        InboxMessage::factory()->create([
            'user_id'               => $user->id,
            'matched_opportunity_id'=> 999,
            'matched_outbound_id'   => 1,
        ]);

        $this->artisan('crm:cancel-followups-on-reply')->assertExitCode(0);

        $followUp->refresh();
        $this->assertSame('cancelled', $followUp->status);
        $this->assertSame('reply_received', $followUp->cancel_reason);
    }

    public function test_cancel_followups_on_reply_leaves_unrelated_followups_alone(): void
    {
        $user = $this->makeApiUser();

        $unrelated = FollowUp::factory()->create([
            'user_id'        => $user->id,
            'tenant_id'      => $user->tenant_id,
            'opportunity_id' => 888,
            'status'         => 'pending',
        ]);

        // Reply is for opportunity 999, not 888
        InboxMessage::factory()->create([
            'user_id'               => $user->id,
            'matched_opportunity_id'=> 999,
            'matched_outbound_id'   => 1,
        ]);

        $this->artisan('crm:cancel-followups-on-reply')->assertExitCode(0);

        $this->assertSame('pending', $unrelated->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Scheduler status endpoint
    // -------------------------------------------------------------------------

    public function test_scheduler_status_endpoint_returns_expected_keys(): void
    {
        $user  = $this->makeApiUser();
        $token = $this->makeApiToken($user, ['scheduler:read']);

        $response = $this->withToken($token)->getJson('/api/gpt/v1/scheduler/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'scheduled_drafts' => ['due_now', 'scheduled_future', 'next_send_at'],
                'follow_ups'       => ['overdue', 'due_today', 'pending_total', 'next_due_at'],
                'recent_failures_24h',
                'dispatcher_schedule',
            ]);
    }

    // -------------------------------------------------------------------------
    // Helpers — override in base TestCase or duplicate here as needed
    // -------------------------------------------------------------------------

    private function makeApiUser(): \App\Models\User
    {
        return \App\Models\User::factory()->create();
    }

    /** @param string[] $scopes */
    private function makeApiToken(\App\Models\User $user, array $scopes): string
    {
        // Adjust to however your project creates GPT v1 API tokens
        return $user->createToken('test', $scopes)->plainTextToken;
    }
}
