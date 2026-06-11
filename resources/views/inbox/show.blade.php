@extends('layouts.app')
@section('title', 'Inbox Message')
@section('page-title', 'Inbox Message')
@section('content')
<div class="max-w-3xl">
    <div class="mb-4"><a href="{{ route('inbox.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Inbox</a></div>
    <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-lg font-bold text-slate-900">{{ $message->subject ?? '(no subject)' }}</h1>
                <div class="flex flex-wrap gap-4 mt-2 text-sm text-slate-500">
                    <span><strong>From:</strong> {{ $message->from_name ? $message->from_name . ' <' . $message->from_email . '>' : $message->from_email }}</span>
                    <span><strong>Received:</strong> {{ $message->received_at->format('M j, Y g:i A') }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @php $sentColors = ['positive'=>'green','neutral'=>'slate','negative'=>'red','unknown'=>'gray']; $sc = $sentColors[$message->sentiment] ?? 'gray'; @endphp
                <span class="px-2.5 py-1 rounded-full text-sm font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($message->sentiment) }}</span>
            </div>
        </div>

        @if($message->matchedContact || $message->matchedOpportunity)
            <div class="flex gap-6 text-sm border border-indigo-100 bg-indigo-50 rounded-lg px-4 py-3">
                @if($message->matchedContact)
                    <span>Contact: <a href="{{ route('contacts.show', $message->matchedContact) }}" class="text-indigo-700 font-medium hover:underline">{{ $message->matchedContact->full_name }}</a></span>
                @endif
                @if($message->matchedOpportunity)
                    <span>Opportunity: <a href="{{ route('opportunities.show', $message->matchedOpportunity) }}" class="text-indigo-700 font-medium hover:underline">{{ Str::limit($message->matchedOpportunity->title, 50) }}</a></span>
                @endif
                @if($message->matchedOutbound)
                    <span>Original email: <a href="{{ route('emails.show', $message->matchedOutbound) }}" class="text-indigo-700 font-medium hover:underline">{{ Str::limit($message->matchedOutbound->subject, 50) }}</a></span>
                @endif
            </div>
        @endif

        <div class="pt-4 border-t border-slate-100">
            <div class="bg-slate-50 rounded-lg p-4 text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $message->body_text ?? strip_tags($message->body_html) }}</div>
        </div>

        @if($message->attachments->isNotEmpty())
            <div class="pt-4 border-t border-slate-100">
                <p class="text-sm font-semibold text-slate-700 mb-2">Attachments</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($message->attachments as $attachment)
                        <a href="{{ route('inbox.attachments.download', [$message, $attachment]) }}"
                           class="bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs px-3 py-1.5 rounded-lg hover:bg-indigo-100">
                            {{ $attachment->file_name }}
                            @if($attachment->file_size)
                                <span class="text-indigo-400">({{ number_format($attachment->file_size / 1024, 1) }} KB)</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex gap-3 pt-4 border-t border-slate-100 flex-wrap">
            <form method="POST" action="{{ route('inbox.review', $message) }}" class="flex gap-2">
                @csrf @method('PATCH')
                <select name="sentiment" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach(['positive'=>'Positive','neutral'=>'Neutral','negative'=>'Negative','unknown'=>'Unknown'] as $val=>$label)
                        <option value="{{ $val }}" {{ $message->sentiment === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="review_status" value="reviewed">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg">Mark Reviewed</button>
            </form>
            @if($message->matchedContact)
                <a href="{{ route('compose') }}?contact_id={{ $message->matchedContact->id }}&opportunity_id={{ $message->matchedOpportunity?->id }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg">Reply</a>
            @endif
        </div>
    </div>
</div>
@endsection
