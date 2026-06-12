@extends('layouts.app')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('content')
<div class="space-y-6">
    {{-- Filters --}}
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from', now()->subDays(30)->format('Y-m-d')) }}" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to', now()->format('Y-m-d')) }}" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg">Generate</button>
        </form>
    </div>

    {{-- Row 1: Summary stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-indigo-600">{{ $stats['emails_sent'] ?? 0 }}</p>
            <p class="text-xs text-slate-500 mt-1">Emails Sent</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-green-600">{{ $stats['replies_received'] ?? 0 }}</p>
            <p class="text-xs text-slate-500 mt-1">Replies Received</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-blue-600">{{ $stats['response_rate'] ?? '0%' }}</p>
            <p class="text-xs text-slate-500 mt-1">Response Rate</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-sky-600">{{ $stats['open_rate'] ?? '0%' }}</p>
            <p class="text-xs text-slate-500 mt-1">Open Rate</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-violet-600">{{ $stats['click_rate'] ?? '0%' }}</p>
            <p class="text-xs text-slate-500 mt-1">Click Rate</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-red-500">{{ $stats['failed_sends'] ?? 0 }}</p>
            <p class="text-xs text-slate-500 mt-1">Failed Sends</p>
        </div>
    </div>

    {{-- Response Rate by Outreach Type --}}
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-slate-800 mb-4">Response Rate by Outreach Type</h2>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Type</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Opportunities</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Emails Sent</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Replies</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Response Rate</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($responseRates ?? [] as $row)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-700">{{ ucfirst($row['type']) }}</td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ $row['opportunities'] }}</td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ $row['sent'] }}</td>
                    <td class="px-4 py-3 text-right text-slate-600">{{ $row['replies'] }}</td>
                    <td class="px-4 py-3 text-right">
                        <span class="font-semibold {{ $row['rate'] > 20 ? 'text-green-600' : ($row['rate'] > 10 ? 'text-yellow-600' : 'text-slate-600') }}">{{ $row['rate'] }}%</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Opportunity Funnel --}}
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-slate-800 mb-4">Opportunity Funnel</h2>
        @php
            $funnelColors = ['draft'=>'slate','active'=>'green','waiting_reply'=>'blue','replied'=>'indigo','interview'=>'purple','offer'=>'emerald','rejected'=>'red'];
            $maxVal = $funnel ? max(array_values($funnel)) : 1;
        @endphp
        <div class="space-y-3">
            @foreach($funnel ?? [] as $status => $count)
            @php $color = $funnelColors[$status] ?? 'slate'; $pct = $maxVal > 0 ? round($count / $maxVal * 100) : 0; @endphp
            <div class="flex items-center gap-3">
                <div class="w-28 text-xs font-medium text-slate-600 text-right">{{ ucwords(str_replace('_',' ',$status)) }}</div>
                <div class="flex-1 bg-slate-100 rounded-full h-6 overflow-hidden">
                    <div class="h-6 rounded-full bg-{{ $color }}-400 flex items-center justify-end pr-2 transition-all" style="width: {{ max($pct, 5) }}%">
                        <span class="text-white text-xs font-bold">{{ $count }}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Sending Activity (last 30 days) --}}
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-slate-800 mb-4">Sending Activity</h2>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Sent</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Failed</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Follow-ups</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($sendingActivity ?? [] as $day)
                <tr>
                    <td class="px-4 py-2 text-slate-700">{{ $day['date'] }}</td>
                    <td class="px-4 py-2 text-right text-green-600 font-medium">{{ $day['sent'] }}</td>
                    <td class="px-4 py-2 text-right text-red-500">{{ $day['failed'] }}</td>
                    <td class="px-4 py-2 text-right text-slate-500">{{ $day['follow_ups'] }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No sending activity in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
