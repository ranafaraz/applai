<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends GptController
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        $user  = $this->apiUser($request);
        $stats = $dashboard->getStats($user);

        // Strip heavy/sensitive fields from the GPT-facing response
        $summary = [
            'total_opportunities'    => $stats['total_opportunities'],
            'active_opportunities'   => $stats['active_opportunities'],
            'waiting_reply'          => $stats['waiting_reply'],
            'follow_ups_due_today'   => $stats['follow_ups_due_today'],
            'follow_ups_overdue'     => $stats['follow_ups_overdue'],
            'scheduled_today'        => $stats['scheduled_today'],
            'replies_needing_review' => $stats['replies_needing_review'],
            'positive_replies'       => $stats['positive_replies'],
            'failed_sends'           => $stats['failed_sends'],
            'deadline_soon'          => $stats['deadline_soon'],
            'total_contacts'         => $stats['total_contacts'],
            'total_emails_sent'      => $stats['total_emails_sent'],
            'outreach_funnel'        => $stats['outreach_funnel'],
            'upcoming_deadlines'     => $stats['upcoming_deadlines'],
        ];

        $nextActions = [];
        if ($stats['follow_ups_due_today'] > 0) {
            $nextActions[] = "Follow up with {$stats['follow_ups_due_today']} contacts due today.";
        }
        if ($stats['replies_needing_review'] > 0) {
            $nextActions[] = "Review {$stats['replies_needing_review']} unread replies.";
        }
        if ($stats['deadline_soon'] > 0) {
            $nextActions[] = "{$stats['deadline_soon']} opportunities have deadlines within 7 days.";
        }
        if ($stats['follow_ups_overdue'] > 0) {
            $nextActions[] = "{$stats['follow_ups_overdue']} follow-ups are overdue.";
        }

        return response()->json([
            'summary'      => $summary,
            'next_actions' => $nextActions,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
