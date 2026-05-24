@extends('layouts.app')
@section('title', 'New Template')
@section('page-title', 'New Email Template')
@section('content')
<div class="max-w-3xl">
    <div class="mb-4"><a href="{{ route('email-templates.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Templates</a></div>
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="mb-5 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-800">
            Use <code class="font-mono bg-blue-100 px-1 rounded">@{{ variable_name }}</code> syntax for dynamic placeholders. e.g. <code class="font-mono bg-blue-100 px-1 rounded">@{{ first_name }}</code>, <code class="font-mono bg-blue-100 px-1 rounded">@{{ company }}</code>, <code class="font-mono bg-blue-100 px-1 rounded">@{{ position }}</code>
        </div>
        <form method="POST" action="{{ route('email-templates.store') }}" class="space-y-5">
            @csrf
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Template Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Job Application - Initial">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach(['initial_outreach'=>'Initial Outreach','follow_up'=>'Follow-up','thank_you'=>'Thank You','networking'=>'Networking','other'=>'Other'] as $val=>$label)
                            <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Subject <span class="text-red-500">*</span></label>
                <input type="text" name="subject" value="{{ old('subject') }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Application for {{"{{"}}position{{"}}"}} at {{"{{"}}company{{"}}"}}">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Body <span class="text-red-500">*</span></label>
                <textarea name="body" rows="15" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Dear {{ '{{' }}first_name{{ '}}' }},&#10;&#10;...">{{ old('body') }}</textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg">Save Template</button>
                <a href="{{ route('email-templates.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
