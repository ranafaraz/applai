@extends('layouts.app')
@section('title', 'Email Detail')
@section('page-title', 'Email Detail')
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Outbox', 'url' => route('emails.index')],
        ['label' => Str::limit($email->subject ?: 'Email', 40)],
    ]" />
@endsection
@section('content')
<div class="max-w-3xl">
    <div class="mb-4"><a href="{{ route('emails.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Outbox</a></div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(!empty($lintIssues))
        <div class="mb-4">@include('partials._email-lint', ['lintIssues' => $lintIssues])</div>
    @endif

    <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-lg font-bold text-slate-900">{{ $email->subject }}</h1>
                <div class="flex flex-wrap gap-3 mt-2 text-sm text-slate-500">
                    <span><strong>To:</strong> {{ $email->to_name ? $email->to_name . ' &lt;' . $email->to_email . '&gt;' : $email->to_email }}</span>
                    <span><strong>From:</strong> {{ $email->emailAccount->email ?? '—' }}</span>
                    <span><strong>Date:</strong> {{ $email->created_at->format('M j, Y g:i A') }}</span>
                </div>
            </div>
            @php
                $statusColors = ['draft'=>'slate','scheduled'=>'purple','queued'=>'blue','sending'=>'yellow','sent'=>'green','failed'=>'red','cancelled'=>'orange'];
                $sc = $statusColors[$email->status] ?? 'slate';
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700 flex-shrink-0">{{ ucfirst($email->status) }}</span>
        </div>
        @if($email->opportunity || $email->contact)
            <div class="flex gap-4 text-sm text-slate-600 pt-2 border-t border-slate-100">
                @if($email->contact) <span>Contact: <a href="{{ route('contacts.show', $email->contact) }}" class="text-indigo-600 hover:underline">{{ $email->contact->full_name }}</a></span> @endif
                @if($email->opportunity) <span>Opportunity: <a href="{{ route('opportunities.show', $email->opportunity) }}" class="text-indigo-600 hover:underline">{{ Str::limit($email->opportunity->title, 50) }}</a></span> @endif
            </div>
        @endif
        @if($email->failure_reason)
            <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                <strong>Failure reason:</strong> {{ $email->failure_reason }}
            </div>
        @endif
        <div class="pt-4 border-t border-slate-100">
            @php
                // Render exactly what the recipient sees: stored body with the
                // signature composed in once (so MCP drafts show their signature
                // and web drafts don't double it). Plain text gets newline-
                // converted so it doesn't collapse into one line.
                $body = $email->composedBody();
                $looksHtml = preg_match('/<\/?[a-z][\s\S]*>/i', $body) === 1;
                $rendered  = $looksHtml ? $body : nl2br(e($body), false);
            @endphp
            <div class="prose prose-sm max-w-none bg-slate-50 rounded-lg p-4 text-sm text-slate-800 leading-relaxed">{!! $rendered !!}</div>
            <p class="text-xs text-slate-400 mt-2">Recipients see the rendered HTML, not the raw markup.</p>
        </div>
        @php
            $localAtts = $email->attachments;
            $apiAtts   = $email->relationLoaded('apiAttachments') ? $email->apiAttachments : collect();
        @endphp
        @if($localAtts->isNotEmpty() || $apiAtts->isNotEmpty())
            <div class="pt-4 border-t border-slate-100">
                <p class="text-sm font-semibold text-slate-700 mb-2">Attachments</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($localAtts as $att)
                        <span class="bg-slate-100 text-slate-700 text-xs px-3 py-1.5 rounded-lg">{{ $att->file_name }}</span>
                    @endforeach
                    @foreach($apiAtts as $att)
                        @if($att->public_url)
                            <a href="{{ $att->public_url }}" target="_blank" rel="noopener"
                               class="bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs px-3 py-1.5 rounded-lg hover:bg-indigo-100">
                                {{ $att->filename }}
                            </a>
                        @else
                            <span class="bg-slate-100 text-slate-700 text-xs px-3 py-1.5 rounded-lg">{{ $att->filename }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
        @php $apiDocLinks = $email->relationLoaded('apiDocumentLinks') ? $email->apiDocumentLinks : collect(); @endphp
        @if($apiDocLinks->isNotEmpty())
            <div class="pt-4 border-t border-slate-100">
                <p class="text-sm font-semibold text-slate-700 mb-2">Linked Documents</p>
                <div class="space-y-2">
                    @foreach($apiDocLinks as $link)
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
            </div>
        @endif
        @php $inboxReplies = $email->relationLoaded('inboxReplies') ? $email->inboxReplies->sortByDesc('received_at') : collect(); @endphp
        @if($inboxReplies->isNotEmpty())
            <div class="pt-4 border-t border-slate-100">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <p class="text-sm font-semibold text-slate-700">Replies Received</p>
                    <span class="text-xs text-slate-500">{{ $inboxReplies->count() }} matched {{ Str::plural('reply', $inboxReplies->count()) }}</span>
                </div>
                <div class="space-y-3">
                    @foreach($inboxReplies as $reply)
                        <a href="{{ route('inbox.show', $reply) }}" class="block border border-indigo-100 bg-indigo-50 rounded-lg p-4 hover:bg-indigo-100">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ $reply->from_name ?: $reply->from_email }}</p>
                                    <p class="text-xs text-slate-500">{{ $reply->from_email }} via {{ $reply->emailAccount->email ?? 'unknown account' }}</p>
                                </div>
                                <span class="text-xs text-slate-500 flex-shrink-0">{{ optional($reply->received_at)->format('M j, Y g:i A') }}</span>
                            </div>
                            <p class="text-sm text-slate-700 mt-2">{{ Str::limit($reply->body_text ?: strip_tags((string) $reply->body_html), 220) }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
        @if(in_array($email->status, ['draft','scheduled']))
            @php
                $hasDanger = collect($lintIssues ?? [])->contains(fn ($i) => ($i['level'] ?? '') === 'danger');
                $confirmMsg = $hasDanger
                    ? 'There are unresolved issues flagged above. Send anyway?'
                    : 'Send this email now to ' . $email->to_email . '?';
            @endphp
            <div class="pt-4 border-t border-slate-100" x-data="{ scheduling: false }">
                @if($email->status === 'scheduled' && $email->scheduled_at)
                    <p class="text-sm text-slate-500 mb-3">Scheduled for <strong class="text-slate-700">{{ $email->scheduled_at->format('M j, Y g:i A') }}</strong>.</p>
                @endif
                <div class="flex flex-wrap gap-3">
                    {{-- Send now --}}
                    <form method="POST" action="{{ route('emails.quick-send', $email) }}" onsubmit="return confirm(@js($confirmMsg))">
                        @csrf
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Send Now
                        </button>
                    </form>
                    {{-- Toggle inline schedule --}}
                    <button type="button" @click="scheduling = !scheduling" class="bg-purple-50 hover:bg-purple-100 text-purple-700 text-sm font-medium px-4 py-2 rounded-lg">
                        {{ $email->status === 'scheduled' ? 'Reschedule' : 'Schedule' }}
                    </button>
                    {{-- Edit --}}
                    <a href="{{ route('emails.edit', $email) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
                        {{ $email->status === 'draft' ? 'Edit Draft' : 'Edit Scheduled' }}
                    </a>
                    {{-- Delete / cancel --}}
                    <form method="POST" action="{{ route('emails.destroy', $email) }}" onsubmit="return confirm('Cancel/delete this email?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium px-4 py-2 rounded-lg">{{ $email->status === 'draft' ? 'Delete Draft' : 'Cancel Scheduled' }}</button>
                    </form>
                </div>
                {{-- Inline schedule picker --}}
                <form method="POST" action="{{ route('emails.quick-schedule', $email) }}" x-show="scheduling" x-cloak class="mt-3 flex flex-wrap items-end gap-3 bg-slate-50 border border-slate-200 rounded-lg p-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Send at</label>
                        <input type="datetime-local" name="scheduled_at" required
                               value="{{ optional($email->scheduled_at)->format('Y-m-d\TH:i') }}"
                               class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Save Schedule</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
