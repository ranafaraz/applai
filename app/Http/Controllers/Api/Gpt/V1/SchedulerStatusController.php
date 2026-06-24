<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\EmailMessage;
use App\Models\FollowUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchedulerStatusController extends GptController
{
    public function show(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $scheduledDraftsDue = EmailMessage::where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->count();

        $scheduledDraftsFuture = EmailMessage::where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->count();

        $followUpsDueNow = FollowUp::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('auto_send', true)
            ->where('due_at', '<=', now())
            ->count();

        $followUpsDueToday = FollowUp::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('auto_send', true)
            ->whereBetween('due_at', [now(), now()->endOfDay()])
            ->count();

        $followUpsPendingTotal = FollowUp::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $recentFailures = EmailMessage::where('user_id', $user->id)
            ->where('status', 'failed')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        $nextScheduledDraft = EmailMessage::where('user_id', $user->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->value('scheduled_at');

        $nextFollowUp = FollowUp::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('auto_send', true)
            ->orderBy('due_at')
            ->value('due_at');

        return response()->json([
            'scheduled_drafts' => [
                'due_now'           => $scheduledDraftsDue,
                'scheduled_future'  => $scheduledDraftsFuture,
                'next_send_at'      => $nextScheduledDraft?->toISOString(),
            ],
            'follow_ups' => [
                'overdue'           => $followUpsDueNow,
                'due_today'         => $followUpsDueToday,
                'pending_total'     => $followUpsPendingTotal,
                'next_due_at'       => $nextFollowUp?->toISOString(),
            ],
            'recent_failures_24h' => $recentFailures,
            'dispatcher_schedule' => [
                'crm:send-scheduled'            => 'every 5 minutes',
                'crm:dispatch-due-followups'    => 'every 5 minutes',
                'crm:cancel-followups-on-reply' => 'every 10 minutes',
                'crm:process-follow-ups'        => 'every 30 minutes',
            ],
        ]);
    }
}
