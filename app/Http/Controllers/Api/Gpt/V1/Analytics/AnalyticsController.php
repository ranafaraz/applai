<?php

namespace App\Http\Controllers\Api\Gpt\V1\Analytics;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\Contact;
use App\Models\ContentItem;
use App\Models\FollowUp;
use App\Models\FreelanceProject;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\Proposal;
use App\Models\ResearchPaper;
use App\Models\ScheduledJob;
use App\Models\Webhook;
use App\Models\YoutubeVideo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only aggregations over the authenticated user's CRM data. No tables of
 * its own — every query is scoped where('user_id', $user->id).
 */
class AnalyticsController extends GptController
{
    /** Cross-domain entity counts plus key status breakdowns. */
    public function summary(Request $request): JsonResponse
    {
        $uid = $this->apiUser($request)->id;

        $data = [
            'counts' => [
                'contacts'           => Contact::where('user_id', $uid)->count(),
                'opportunities'      => Opportunity::where('user_id', $uid)->count(),
                'follow_ups'         => FollowUp::where('user_id', $uid)->count(),
                'proposals'          => Proposal::where('user_id', $uid)->count(),
                'content_items'      => ContentItem::where('user_id', $uid)->count(),
                'research_papers'    => ResearchPaper::where('user_id', $uid)->count(),
                'youtube_videos'     => YoutubeVideo::where('user_id', $uid)->count(),
                'freelance_projects' => FreelanceProject::where('user_id', $uid)->count(),
                'pipelines'          => Pipeline::where('user_id', $uid)->count(),
                'scheduled_jobs'     => ScheduledJob::where('user_id', $uid)->count(),
                'webhooks'           => Webhook::where('user_id', $uid)->count(),
            ],
            'by_status' => [
                'opportunities'      => $this->statusCounts(Opportunity::where('user_id', $uid)),
                'proposals'          => $this->statusCounts(Proposal::where('user_id', $uid)),
                'freelance_projects' => $this->statusCounts(FreelanceProject::where('user_id', $uid)),
                'content_items'      => $this->statusCounts(ContentItem::where('user_id', $uid)),
            ],
        ];

        $this->audit($request, 'analytics_summary', null, null, 'low');

        return response()->json(['data' => $data]);
    }

    /** Opportunity pipeline breakdown by status and priority. */
    public function opportunities(Request $request): JsonResponse
    {
        $uid = $this->apiUser($request)->id;

        $data = [
            'total'       => Opportunity::where('user_id', $uid)->count(),
            'by_status'   => $this->statusCounts(Opportunity::where('user_id', $uid)),
            'by_priority' => $this->groupCounts(Opportunity::where('user_id', $uid), 'priority'),
        ];

        $this->audit($request, 'analytics_opportunities', null, null, 'low');

        return response()->json(['data' => $data]);
    }

    /** Revenue-oriented aggregations across proposals and freelance projects. */
    public function revenue(Request $request): JsonResponse
    {
        $uid = $this->apiUser($request)->id;

        $data = [
            'proposals' => [
                'by_status'      => $this->statusCounts(Proposal::where('user_id', $uid)),
                'total_amount'   => (float) Proposal::where('user_id', $uid)->sum('amount'),
                'accepted_amount' => (float) Proposal::where('user_id', $uid)->where('status', 'accepted')->sum('amount'),
            ],
            'freelance' => [
                'by_status'    => $this->statusCounts(FreelanceProject::where('user_id', $uid)),
                'total_budget' => (float) FreelanceProject::where('user_id', $uid)->sum('budget'),
                'hours_logged' => (float) FreelanceProject::where('user_id', $uid)->sum('hours_logged'),
            ],
        ];

        $this->audit($request, 'analytics_revenue', null, null, 'low');

        return response()->json(['data' => $data]);
    }

    /** Content + YouTube publishing analytics. */
    public function content(Request $request): JsonResponse
    {
        $uid = $this->apiUser($request)->id;

        $data = [
            'content_items' => [
                'total'     => ContentItem::where('user_id', $uid)->count(),
                'by_status' => $this->statusCounts(ContentItem::where('user_id', $uid)),
            ],
            'youtube_videos' => [
                'total'         => YoutubeVideo::where('user_id', $uid)->count(),
                'by_status'     => $this->statusCounts(YoutubeVideo::where('user_id', $uid)),
                'total_views'   => (int) YoutubeVideo::where('user_id', $uid)->sum('view_count'),
                'total_likes'   => (int) YoutubeVideo::where('user_id', $uid)->sum('like_count'),
                'total_comments' => (int) YoutubeVideo::where('user_id', $uid)->sum('comment_count'),
            ],
        ];

        $this->audit($request, 'analytics_content', null, null, 'low');

        return response()->json(['data' => $data]);
    }

    /** Group a scoped query by `status` → ['status_value' => count]. */
    private function statusCounts(Builder $query): array
    {
        return $this->groupCounts($query, 'status');
    }

    /** Group a scoped query by an arbitrary column → ['value' => count]. */
    private function groupCounts(Builder $query, string $column): array
    {
        return $query
            ->selectRaw("{$column} as k, COUNT(*) as c")
            ->groupBy($column)
            ->pluck('c', 'k')
            ->map(fn ($c) => (int) $c)
            ->toArray();
    }
}
