<?php

namespace App\Http\Controllers\Api\App;

use App\Models\Contact;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Support\OpportunityStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only analytics for the mobile app (§4.6). No models or migrations of
 * its own — all queries scoped to the authenticated user via where('user_id').
 */
class AnalyticsController extends AppController
{
    /**
     * Key metrics overview: totals, response/win rates, pending work.
     *
     * response_rate = opps that reached replied|interview|offer|won
     *                 ÷ opps that left draft (applied → won)
     * win_rate      = won ÷ (all non-archived)
     */
    public function overview(Request $request): JsonResponse
    {
        $uid        = $request->user()->id;
        $counts     = $this->stageCounts($uid);
        $total      = array_sum($counts);

        $activeStages    = ['applied', 'replied', 'interview', 'offer'];
        $respondedStages = ['replied', 'interview', 'offer', 'won'];
        $progressedStages = ['applied', 'replied', 'interview', 'offer', 'won'];

        $activeCount  = array_sum(array_intersect_key($counts, array_flip($activeStages)));
        $responded    = array_sum(array_intersect_key($counts, array_flip($respondedStages)));
        $progressed   = array_sum(array_intersect_key($counts, array_flip($progressedStages)));
        $nonArchived  = $total - ($counts['archived'] ?? 0);

        $responseRate = $progressed > 0 ? round($responded / $progressed, 4) : 0.0;
        $winRate      = $nonArchived > 0 ? round(($counts['won'] ?? 0) / $nonArchived, 4) : 0.0;

        $pendingFollowUps = FollowUp::where('user_id', $uid)->where('status', 'pending')->count();
        $overdueFollowUps = FollowUp::where('user_id', $uid)
            ->where('status', 'pending')
            ->whereDate('due_at', '<', Carbon::today())
            ->count();

        return $this->data([
            'opportunities' => [
                'total'         => $total,
                'active'        => $activeCount,
                'response_rate' => $responseRate,
                'win_rate'      => $winRate,
            ],
            'contacts' => [
                'total'  => Contact::where('user_id', $uid)->count(),
                'active' => Contact::where('user_id', $uid)->where('status', 'active')->count(),
            ],
            'follow_ups' => [
                'pending' => $pendingFollowUps,
                'overdue' => $overdueFollowUps,
            ],
        ]);
    }

    /**
     * Pipeline funnel: count + % of total + conversion from prior stage
     * for each canonical OpportunityStage in order.
     */
    public function pipeline(Request $request): JsonResponse
    {
        $uid    = $request->user()->id;
        $counts = $this->stageCounts($uid);
        $total  = array_sum($counts);

        $funnel    = [];
        $prevCount = null;
        foreach (OpportunityStage::STAGES as $stage) {
            $count    = $counts[$stage] ?? 0;
            $funnel[] = [
                'stage'      => $stage,
                'count'      => $count,
                'pct'        => $total > 0 ? round($count / $total * 100, 1) : 0.0,
                'conversion' => ($prevCount !== null && $prevCount > 0)
                    ? round($count / $prevCount * 100, 1)
                    : null,
            ];
            $prevCount = $count;
        }

        return $this->data([
            'total'  => $total,
            'stages' => $funnel,
        ]);
    }

    /**
     * Activity over time: new opportunities + outbound emails per day.
     * period = 7d | 30d | 90d (default 30d). Returns a zero-filled series.
     */
    public function activity(Request $request): JsonResponse
    {
        $uid = $request->user()->id;

        $periodDays = match ($request->query('period', '30d')) {
            '7d'  => 7,
            '90d' => 90,
            default => 30,
        };

        $start = Carbon::today()->subDays($periodDays - 1)->startOfDay();

        $appCounts = Opportunity::where('user_id', $uid)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->pluck('cnt', 'day')
            ->map(fn ($c) => (int) $c);

        $emailCounts = EmailMessage::where('user_id', $uid)
            ->where('direction', 'outbound')
            ->whereIn('status', ['sent', 'scheduled'])
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->pluck('cnt', 'day')
            ->map(fn ($c) => (int) $c);

        return $this->data([
            'period_days'  => $periodDays,
            'applications' => $this->daySeries($start, $periodDays, $appCounts),
            'emails_sent'  => $this->daySeries($start, $periodDays, $emailCounts),
        ]);
    }

    /** Group opportunities by DB status, normalize to canonical stages. */
    private function stageCounts(int $uid): array
    {
        $counts = array_fill_keys(OpportunityStage::STAGES, 0);

        Opportunity::where('user_id', $uid)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get()
            ->each(function ($row) use (&$counts) {
                $stage = OpportunityStage::normalize($row->status);
                $counts[$stage] += (int) $row->cnt;
            });

        return $counts;
    }

    /** Build a zero-filled day series and merge in actual DB counts. */
    private function daySeries(Carbon $start, int $days, Collection $data): array
    {
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date     = $start->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $date, 'count' => $data->get($date, 0)];
        }

        return $series;
    }
}
