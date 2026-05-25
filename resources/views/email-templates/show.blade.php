@extends('layouts.app')
@section('title', $template->name)
@section('page-title', 'Email Template')
@section('content')
<div class="max-w-3xl">
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ route('email-templates.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Templates</a>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                @php $typeColors = ['initial_outreach'=>'blue','follow_up'=>'orange','thank_you'=>'green','networking'=>'purple','other'=>'slate']; $tc = $typeColors[$template->type] ?? 'slate'; @endphp
                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $tc }}-100 text-{{ $tc }}-700 mb-2 inline-block">{{ ucwords(str_replace('_', ' ', $template->type)) }}</span>
                <h2 class="text-lg font-semibold text-slate-800">{{ $template->name }}</h2>
                <p class="text-xs text-slate-400 mt-0.5">Used {{ $template->times_used }} time{{ $template->times_used !== 1 ? 's' : '' }} &middot; Updated {{ $template->updated_at->format('M j, Y') }}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('email-templates.edit', $template) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Edit</a>
                <form method="POST" action="{{ route('email-templates.duplicate', $template) }}">
                    @csrf
                    <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg">Duplicate</button>
                </form>
            </div>
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Subject</p>
            <p class="text-sm text-slate-800 bg-slate-50 border border-slate-200 rounded-lg px-4 py-2 font-mono">{{ $template->subject }}</p>
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Body</p>
            <div class="prose prose-sm max-w-none text-sm text-slate-800 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 overflow-x-auto">{!! $template->body !!}</div>
        </div>

        @if($template->variables && count($template->variables) > 0)
        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Variables</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($template->variables as $var)
                    <span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-1 rounded font-mono">{{ '{{' . $var . '}}' }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
