@extends('layouts.app')

@section('title', $contact->first_name . ' ' . $contact->last_name)
@section('page-title', 'Contact Details')

@section('content')
{{-- Header --}}
<div class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-indigo-100 rounded-full flex items-center justify-center text-xl font-bold text-indigo-700 flex-shrink-0">
                {{ strtoupper(substr($contact->first_name ?? 'C', 0, 1)) }}
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $contact->first_name }} {{ $contact->last_name }}</h1>
                <p class="text-sm text-slate-500">{{ $contact->email }}</p>
                @if($contact->company || $contact->job_title)
                    <p class="text-sm text-slate-600">{{ $contact->job_title }}@if($contact->company && $contact->job_title) <span class="text-slate-400 mx-1">at</span>@endif{{ $contact->company }}</p>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @php
                $statusBadge = match($contact->status) {
                    'active'     => 'bg-green-100 text-green-700',
                    'suppressed' => 'bg-red-100 text-red-700',
                    'bounced'    => 'bg-orange-100 text-orange-700',
                    default      => 'bg-slate-100 text-slate-600',
                };
            @endphp
            <span class="text-sm font-medium px-3 py-1 rounded-full {{ $statusBadge }}">{{ ucfirst($contact->status ?? 'active') }}</span>
            <a href="{{ route('contacts.edit', $contact) }}" class="inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
            <a href="{{ route('compose') . '?contact_id=' . $contact->id }}" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Compose Email
            </a>
            @if($contact->status !== 'suppressed')
                <form method="POST" action="{{ route('suppression-list.store') }}" onsubmit="return confirm('Suppress this contact?')">
                    @csrf
                    <input type="hidden" name="email" value="{{ $contact->email }}">
                    <input type="hidden" name="reason" value="manual">
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        Suppress
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Contact Details --}}
    <div class="mt-5 grid grid-cols-2 md:grid-cols-4 gap-4 pt-5 border-t border-slate-100">
        @if($contact->phone)
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-0.5">Phone</p>
                <p class="text-sm text-slate-700">{{ $contact->phone }}</p>
            </div>
        @endif
        @if($contact->city || $contact->country)
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-0.5">Location</p>
                <p class="text-sm text-slate-700">{{ collect([$contact->city, $contact->country])->filter()->implode(', ') }}</p>
            </div>
        @endif
        @if($contact->source)
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-0.5">Source</p>
                <p class="text-sm text-slate-700">{{ ucfirst($contact->source) }}</p>
            </div>
        @endif
        @if($contact->linkedin_url)
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-0.5">LinkedIn</p>
                <a href="{{ $contact->linkedin_url }}" target="_blank" class="text-sm text-indigo-600 hover:underline truncate block">View Profile</a>
            </div>
        @endif
    </div>
</div>

{{-- Tabs --}}
<div x-data="{ tab: 'timeline' }">
    <div class="flex gap-1 mb-4 border-b border-slate-200">
        <button @click="tab = 'timeline'" :class="tab === 'timeline' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px">Timeline</button>
        <button @click="tab = 'opportunities'" :class="tab === 'opportunities' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px">Opportunities</button>
        <button @click="tab = 'emails'" :class="tab === 'emails' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px">Emails</button>
        <button @click="tab = 'documents'" :class="tab === 'documents' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px">Documents</button>
    </div>

    {{-- Timeline Tab --}}
    <div x-show="tab === 'timeline'" class="bg-white border border-slate-200 rounded-xl p-6">
        @if(isset($events) && $events->count())
            <div class="space-y-4">
                @foreach($events as $event)
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="w-0.5 bg-slate-200 flex-1 mt-1"></div>
                        </div>
                        <div class="pb-4 flex-1">
                            <p class="text-sm font-medium text-slate-800">{{ $event->description }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $event->created_at->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            @include('partials._empty-state', ['message' => 'No timeline events yet.', 'action_url' => null, 'action_text' => null])
        @endif
    </div>

    {{-- Opportunities Tab --}}
    <div x-show="tab === 'opportunities'" class="bg-white border border-slate-200 rounded-xl overflow-hidden" x-cloak>
        @if(isset($opportunities) && $opportunities->count())
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Title</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Type</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Deadline</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($opportunities as $opp)
                        <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('opportunities.show', $opp) }}'">
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $opp->title }}</td>
                            <td class="px-5 py-3"><span class="text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ ucfirst($opp->type) }}</span></td>
                            <td class="px-5 py-3"><span class="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ ucfirst(str_replace('_', ' ', $opp->status)) }}</span></td>
                            <td class="px-5 py-3 text-xs text-slate-500">{{ $opp->deadline ? $opp->deadline->format('M d, Y') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="p-8 text-center text-slate-400 text-sm">No opportunities linked to this contact.</div>
        @endif
    </div>

    {{-- Emails Tab --}}
    <div x-show="tab === 'emails'" class="bg-white border border-slate-200 rounded-xl overflow-hidden" x-cloak>
        @if(isset($emails) && $emails->count())
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Subject</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Sent At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($emails as $email)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $email->subject }}</td>
                            <td class="px-5 py-3"><span class="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ ucfirst($email->status) }}</span></td>
                            <td class="px-5 py-3 text-xs text-slate-500">{{ $email->sent_at ? $email->sent_at->format('M d, Y H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="p-8 text-center text-slate-400 text-sm">No emails sent to this contact.</div>
        @endif
    </div>

    {{-- Documents Tab --}}
    <div x-show="tab === 'documents'" x-cloak class="bg-white border border-slate-200 rounded-xl p-6">
        @php $apiLinks = $contact->apiDocumentLinks; @endphp
        @if($apiLinks->isEmpty())
            <p class="text-center text-slate-400 py-8 text-sm">No documents attached.</p>
        @else
            <div class="space-y-2">
                @foreach($apiLinks as $link)
                @php $doc = $link->document; $ver = $doc?->currentVersion; @endphp
                @if($doc)
                <div class="flex items-center justify-between p-3 rounded-lg border border-slate-200">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $doc->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ ucfirst(str_replace('_', ' ', $doc->document_type ?? 'other')) }}
                            @if($ver) &bull; {{ $ver->original_filename }} &bull; {{ number_format($ver->size_bytes / 1024, 1) }} KB @endif
                            @if($doc->is_sensitive) &bull; <span class="text-amber-600 font-medium">Sensitive</span> @endif
                        </p>
                    </div>
                    @if($ver?->public_url)
                        <a href="{{ $ver->public_url }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline">View</a>
                    @else
                        <span class="text-xs text-slate-400">Stored</span>
                    @endif
                </div>
                @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
