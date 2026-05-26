@extends('layouts.app')
@section('title', 'Follow-ups')
@section('page-title', 'Follow-ups')
@section('content')
<div>
    {{-- Filters --}}
    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
        <form method="GET" action="{{ route('follow-ups.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select name="status" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All</option>
                    @foreach(['pending','sent','cancelled','skipped'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg">Filter</button>
                <a href="{{ route('follow-ups.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-1.5 rounded-lg">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        @if($followUps->isEmpty() && $scheduledEmailFollowUps->isEmpty())
            <div class="text-center py-16">
                <p class="text-slate-500 font-medium">No follow-ups scheduled.</p>
                <p class="text-sm text-slate-400 mt-1">Follow-ups are created automatically when you send emails with the follow-up option enabled.</p>
            </div>
        @else
            @if($scheduledEmailFollowUps->isNotEmpty())
                <div class="px-4 py-4 border-b border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Scheduled follow-up emails</h3>
                    <div class="space-y-2">
                        @foreach($scheduledEmailFollowUps as $email)
                            @php
                                $statusColors = ['scheduled'=>'yellow','queued'=>'blue','sent'=>'green','failed'=>'red','cancelled'=>'gray'];
                                $sc = $statusColors[$email->status] ?? 'gray';
                            @endphp
                            <a href="{{ route('emails.show', $email) }}" class="flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                                <div>
                                    <p class="text-sm font-medium text-slate-800">{{ $email->subject }}</p>
                                    <p class="text-xs text-slate-500">
                                        To: {{ $email->to_email }}
                                        @if($email->opportunity)
                                            &bull; {{ Str::limit($email->opportunity->title, 44) }}
                                        @endif
                                        @if($email->scheduled_at)
                                            &bull; {{ $email->scheduled_at->format('M j, Y g:i A') }}
                                        @endif
                                    </p>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($email->status) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($followUps->isNotEmpty())
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Opportunity</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Due Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Follow-up #</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($followUps as $fu)
                    @php
                        $isOverdue = $fu->status === 'pending' && $fu->due_at->isPast();
                        $statusColors = ['pending'=>'yellow','sent'=>'green','cancelled'=>'red','skipped'=>'gray'];
                        $sc = $statusColors[$fu->status] ?? 'gray';
                    @endphp
                    <tr class="{{ $isOverdue ? 'bg-red-50' : 'hover:bg-slate-50' }}">
                        <td class="px-4 py-3">
                            @if($fu->opportunity)
                                <a href="{{ route('opportunities.show', $fu->opportunity) }}" class="text-indigo-600 hover:underline">{{ Str::limit($fu->opportunity->title, 40) }}</a>
                            @else <span class="text-slate-400">—</span> @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($fu->contact) <a href="{{ route('contacts.show', $fu->contact) }}" class="text-indigo-600 hover:underline">{{ $fu->contact->full_name }}</a>
                            @else <span class="text-slate-400">—</span> @endif
                        </td>
                        <td class="px-4 py-3 {{ $isOverdue ? 'text-red-700 font-semibold' : 'text-slate-600' }}">
                            {{ $fu->due_at->format('M j, Y g:i A') }}
                            @if($isOverdue) <span class="text-xs font-normal text-red-500">(overdue)</span> @endif
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600">#{{ $fu->follow_up_number }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($fu->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                            @if($fu->status === 'pending')
                                <form method="POST" action="{{ route('follow-ups.cancel', $fu) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 px-2 py-1 rounded hover:bg-red-50">Cancel</button>
                                </form>
                                <button onclick="document.getElementById('reschedule-{{ $fu->id }}').classList.toggle('hidden')" class="text-xs text-indigo-600 hover:underline px-2 py-1 rounded hover:bg-slate-100">Reschedule</button>
                            @endif
                        </td>
                    </tr>
                    @if($fu->status === 'pending')
                    <tr id="reschedule-{{ $fu->id }}" class="hidden bg-indigo-50">
                        <td colspan="6" class="px-4 py-3">
                            <form method="POST" action="{{ route('follow-ups.reschedule', $fu) }}" class="flex items-center gap-3">
                                @csrf @method('PATCH')
                                <label class="text-sm text-slate-700">New date:</label>
                                <input type="datetime-local" name="due_at" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Save</button>
                            </form>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
            @if($followUps->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">{{ $followUps->withQueryString()->links() }}</div>
            @endif
            @endif
        @endif
    </div>
</div>
@endsection
