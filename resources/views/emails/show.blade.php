@extends('layouts.app')
@section('title', 'Email Detail')
@section('page-title', 'Email Detail')
@section('content')
<div class="max-w-3xl">
    <div class="mb-4"><a href="{{ route('emails.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Outbox</a></div>
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
            <div class="bg-slate-50 rounded-lg p-4 text-sm text-slate-700 whitespace-pre-line font-mono leading-relaxed">{{ $email->body }}</div>
        </div>
        @if($email->attachments->isNotEmpty())
            <div class="pt-4 border-t border-slate-100">
                <p class="text-sm font-semibold text-slate-700 mb-2">Attachments</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($email->attachments as $att)
                        <span class="bg-slate-100 text-slate-700 text-xs px-3 py-1.5 rounded-lg">{{ $att->file_name }}</span>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="flex gap-3 pt-4 border-t border-slate-100">
            @if(in_array($email->status, ['draft','scheduled']))
                <form method="POST" action="{{ route('emails.destroy', $email) }}" onsubmit="return confirm('Cancel/delete this email?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium px-4 py-2 rounded-lg">{{ $email->status === 'draft' ? 'Delete Draft' : 'Cancel Scheduled' }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
