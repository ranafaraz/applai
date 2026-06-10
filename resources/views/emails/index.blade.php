@extends('layouts.app')

@section('title', 'Outbox')
@section('page-title', 'Outbox')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Outbox']]" />
@endsection

@section('content')
<div x-data="{ tab: '{{ request('tab', 'outbox') }}' }">
    {{-- Tabs --}}
    <div class="flex gap-1 mb-6 border-b border-slate-200">
        <button @click="tab = 'outbox'" :class="tab === 'outbox' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px">
            Outbox
            @if(isset($outboxCount) && $outboxCount > 0)
                <span class="ml-1.5 bg-slate-200 text-slate-600 text-xs rounded-full px-1.5 py-0.5">{{ $outboxCount }}</span>
            @endif
        </button>
        <button @click="tab = 'scheduled'" :class="tab === 'scheduled' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px">
            Scheduled
            @if(isset($scheduledCount) && $scheduledCount > 0)
                <span class="ml-1.5 bg-purple-100 text-purple-600 text-xs rounded-full px-1.5 py-0.5">{{ $scheduledCount }}</span>
            @endif
        </button>
        <button @click="tab = 'drafts'" :class="tab === 'drafts' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px">
            Drafts
            @if(isset($draftsCount) && $draftsCount > 0)
                <span class="ml-1.5 bg-slate-200 text-slate-600 text-xs rounded-full px-1.5 py-0.5">{{ $draftsCount }}</span>
            @endif
        </button>
        <button @click="tab = 'failed'" :class="tab === 'failed' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px">
            Failed
            @if(isset($failedCount) && $failedCount > 0)
                <span class="ml-1.5 bg-red-100 text-red-600 text-xs rounded-full px-1.5 py-0.5">{{ $failedCount }}</span>
            @endif
        </button>
    </div>

    @php
        $tableHeaders = ['Subject', 'To', 'From Account', 'Status', 'Date', 'Actions'];
    @endphp

    {{-- Outbox Tab --}}
    <div x-show="tab === 'outbox'" class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        @foreach($tableHeaders as $h)
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sent ?? [] as $email)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-medium text-slate-800 max-w-xs truncate">{{ $email->subject }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $email->to_email }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $email->emailAccount?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5"><span class="text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700">Sent</span></td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">{{ $email->sent_at?->format('M d, Y H:i') ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-xs"><a href="{{ route('emails.show', $email) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400 text-sm">No sent emails.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Scheduled Tab --}}
    <div x-show="tab === 'scheduled'" class="bg-white border border-slate-200 rounded-xl overflow-hidden" x-cloak>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        @foreach($tableHeaders as $h)
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($scheduled ?? [] as $email)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-medium text-slate-800 max-w-xs truncate">{{ $email->subject }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $email->to_email }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $email->emailAccount?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5"><span class="text-xs font-medium px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">Scheduled</span></td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">{{ $email->scheduled_at?->format('M d, Y H:i') ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex gap-3 text-xs">
                                    <a href="{{ route('emails.show', $email) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                                    <a href="{{ route('emails.edit', $email) }}" class="text-slate-700 hover:text-slate-900 font-medium">Edit</a>
                                    <form method="POST" action="{{ route('emails.destroy', $email) }}" onsubmit="return confirm('Cancel this scheduled email?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Cancel</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400 text-sm">No scheduled emails.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Drafts Tab --}}
    <div x-show="tab === 'drafts'" class="bg-white border border-slate-200 rounded-xl overflow-hidden" x-cloak>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        @foreach($tableHeaders as $h)
                            <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($drafts ?? [] as $email)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-medium text-slate-800 max-w-xs truncate">{{ $email->subject ?: '(No subject)' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $email->to_email ?: '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $email->emailAccount?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5"><span class="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Draft</span></td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">{{ $email->updated_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex gap-3 text-xs">
                                    <a href="{{ route('emails.edit', $email) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                                    <a href="{{ route('emails.show', $email) }}" class="text-slate-700 hover:text-slate-900 font-medium">View</a>
                                    <form method="POST" action="{{ route('emails.destroy', $email) }}" onsubmit="return confirm('Delete this draft?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400 text-sm">No drafts.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Failed Tab --}}
    <div x-show="tab === 'failed'" class="bg-white border border-slate-200 rounded-xl overflow-hidden" x-cloak>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Subject</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">To</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">From Account</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Error</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($failed ?? [] as $email)
                        <tr class="hover:bg-red-50 bg-red-50/30">
                            <td class="px-5 py-3.5 font-medium text-slate-800 max-w-xs truncate">{{ $email->subject }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $email->to_email }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $email->emailAccount?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-xs text-red-600 max-w-xs truncate">{{ $email->error_message ?? 'Unknown error' }}</td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">{{ $email->updated_at?->format('M d, Y H:i') ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('emails.show', $email) }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400 text-sm">No failed emails.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
