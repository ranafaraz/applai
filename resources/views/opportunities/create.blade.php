@extends('layouts.app')
@section('title', 'Add Opportunity')
@section('page-title', 'Add Opportunity')
@section('content')
<div class="max-w-2xl">
    <div class="mb-4"><a href="{{ route('opportunities.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Opportunities</a></div>
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ route('opportunities.store') }}" class="space-y-5">
            @csrf
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required placeholder="e.g. Software Engineer at Google" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Type <span class="text-red-500">*</span></label>
                    <select name="type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach(['job'=>'Job','scholarship'=>'Scholarship','research'=>'Research Collaboration','grant'=>'Grant / Funding','networking'=>'Networking'] as $val=>$label)
                            <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach(['urgent'=>'Urgent','high'=>'High','medium'=>'Medium','low'=>'Low'] as $val=>$label)
                            <option value="{{ $val }}" {{ old('priority','medium') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Organization</label>
                    <input type="text" name="organization" value="{{ old('organization') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Company / University / Foundation">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach(['draft'=>'Draft','active'=>'Active','waiting_reply'=>'Waiting Reply','replied'=>'Replied','interview'=>'Interview','offer'=>'Offer','rejected'=>'Rejected'] as $val=>$label)
                            <option value="{{ $val }}" {{ old('status','draft') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Deadline</label>
                    <input type="date" name="deadline" value="{{ old('deadline') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">URL</label>
                    <input type="url" name="url" value="{{ old('url') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="https://...">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="4" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Job description, scholarship details, research topic...">{{ old('description') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">Save Opportunity</button>
                <a href="{{ route('opportunities.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
