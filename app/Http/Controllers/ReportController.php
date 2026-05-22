<?php

namespace App\Http\Controllers;

use App\Models\EmailMessage;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $dateFrom = $request->input('date_from') ? now()->parse($request->input('date_from'))->startOfDay() : now()->subDays(29)->startOfDay();
        $dateTo   = $request->input('date_to')   ? now()->parse($request->input('date_to'))->endOfDay()   : now()->endOfDay();

        $emailsSent = EmailMessage::where('user_id', $userId)->where('status', 'sent')->whereBetween('sent_at', [$dateFrom, $dateTo])->count();
        $repliesReceived = InboxMessage::where('user_id', $userId)->whereNotNull('matched_outbound_id')->whereBetween('received_at', [$dateFrom, $dateTo])->count();
        $failedSends = EmailMessage::where('user_id', $userId)->where('status', 'failed')->whereBetween('failed_at', [$dateFrom, $dateTo])->count();
        $responseRate = $emailsSent > 0 ? round($repliesReceived / $emailsSent * 100, 1) . '%' : '0%';

        $stats = compact('emailsSent', 'repliesReceived', 'failedSends', 'responseRate');
        $stats['emails_sent'] = $emailsSent;
        $stats['replies_received'] = $repliesReceived;
        $stats['failed_sends'] = $failedSends;
        $stats['response_rate'] = $responseRate;

        $funnel = Opportunity::where('user_id', $userId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $sentByType = EmailMessage::where('user_id', $userId)->where('status', 'sent')
            ->join('opportunities', 'email_messages.opportunity_id', '=', 'opportunities.id')
            ->select('opportunities.type', DB::raw('COUNT(*) as sent'))
            ->groupBy('opportunities.type')->pluck('sent', 'type');

        $repliesByType = InboxMessage::where('inbox_messages.user_id', $userId)->whereNotNull('matched_opportunity_id')
            ->join('opportunities', 'inbox_messages.matched_opportunity_id', '=', 'opportunities.id')
            ->select('opportunities.type', DB::raw('COUNT(*) as replies'))
            ->groupBy('opportunities.type')->pluck('replies', 'type');

        $oppsByType = Opportunity::where('user_id', $userId)->select('type', DB::raw('COUNT(*) as cnt'))->groupBy('type')->pluck('cnt', 'type');

        $responseRates = collect(['job','scholarship','research','grant','networking'])->map(fn($type) => [
            'type' => $type,
            'opportunities' => $oppsByType[$type] ?? 0,
            'sent' => $sentByType[$type] ?? 0,
            'replies' => $repliesByType[$type] ?? 0,
            'rate' => ($sentByType[$type] ?? 0) > 0 ? round(($repliesByType[$type] ?? 0) / $sentByType[$type] * 100, 1) : 0,
        ])->filter(fn($r) => $r['opportunities'] > 0 || $r['sent'] > 0)->values();

        $sendingActivity = EmailMessage::where('user_id', $userId)->whereBetween('sent_at', [$dateFrom, $dateTo])
            ->where('status', 'sent')
            ->select(DB::raw('DATE(sent_at) as date'), DB::raw('COUNT(*) as sent'))
            ->groupBy(DB::raw('DATE(sent_at)'))->orderBy('date')->get()
            ->map(fn($r) => ['date' => $r->date, 'sent' => $r->sent, 'failed' => 0, 'follow_ups' => 0]);

        return view('reports.index', compact('stats', 'funnel', 'responseRates', 'sendingActivity'));
    }

    /**
     * Emails sent per day for the requested date range.
     */
    public function sendingActivity(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $from = $request->input('date_from') ?? now()->subDays(29)->startOfDay();
        $to   = $request->input('date_to')   ?? now()->endOfDay();

        $rows = EmailMessage::where('user_id', $request->user()->id)
            ->where('direction', 'outbound')
            ->where('status', 'sent')
            ->whereBetween('sent_at', [$from, $to])
            ->select(DB::raw('DATE(sent_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(sent_at)'))
            ->orderBy('date')
            ->get();

        return response()->json($rows);
    }

    /**
     * Response rates broken down by opportunity type.
     */
    public function responseRates(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $sent = EmailMessage::where('user_id', $userId)
            ->where('direction', 'outbound')
            ->where('status', 'sent')
            ->select('opportunity_id', DB::raw('COUNT(*) as sent'))
            ->groupBy('opportunity_id')
            ->pluck('sent', 'opportunity_id');

        $replied = InboxMessage::where('user_id', $userId)
            ->whereNotNull('matched_outbound_id')
            ->select('matched_opportunity_id', DB::raw('COUNT(*) as replies'))
            ->groupBy('matched_opportunity_id')
            ->pluck('replies', 'matched_opportunity_id');

        $byType = Opportunity::where('user_id', $userId)
            ->whereIn('id', $sent->keys())
            ->select('id', 'type')
            ->get()
            ->groupBy('type')
            ->map(function ($opps) use ($sent, $replied) {
                $totalSent    = $opps->sum(fn ($o) => $sent[$o->id] ?? 0);
                $totalReplied = $opps->sum(fn ($o) => $replied[$o->id] ?? 0);
                return [
                    'type'        => $opps->first()->type ?? 'unknown',
                    'sent'        => $totalSent,
                    'replies'     => $totalReplied,
                    'rate'        => $totalSent > 0 ? round($totalReplied / $totalSent * 100, 1) : 0,
                ];
            })
            ->values();

        return response()->json($byType);
    }

    /**
     * Opportunity funnel – count per status.
     */
    public function opportunityFunnel(Request $request): JsonResponse
    {
        $rows = Opportunity::where('user_id', $request->user()->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json($rows);
    }

    /**
     * Top contacts by number of emails sent.
     */
    public function topContacts(Request $request): JsonResponse
    {
        $rows = EmailMessage::where('user_id', $request->user()->id)
            ->where('direction', 'outbound')
            ->where('status', 'sent')
            ->whereNotNull('contact_id')
            ->select('contact_id', DB::raw('COUNT(*) as emails_sent'))
            ->groupBy('contact_id')
            ->orderByDesc('emails_sent')
            ->limit(20)
            ->with('contact:id,first_name,last_name,email,company')
            ->get()
            ->map(fn ($row) => [
                'contact'     => $row->contact,
                'emails_sent' => $row->emails_sent,
            ]);

        return response()->json($rows);
    }
}
