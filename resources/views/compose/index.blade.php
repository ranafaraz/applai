@extends('layouts.app')

@section('title', 'Compose Email')
@section('page-title', 'Compose Email')

@section('content')
<div class="max-w-3xl">
    <form method="POST" action="{{ route('compose.send') }}" x-data="{ sendMode: 'now', scheduleFollowUp: false }">
        @csrf

        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Recipients & Linking --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Recipient & Links</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">To (Email) <span class="text-red-500">*</span></label>
                    <input type="email" name="to_email" value="{{ old('to_email') }}" required placeholder="recipient@example.com" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('to_email') border-red-400 @enderror">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">From Account <span class="text-red-500">*</span></label>
                        <select name="email_account_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select account...</option>
                            @foreach($emailAccounts ?? [] as $account)
                                <option value="{{ $account->id }}" {{ old('email_account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }} ({{ $account->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Link to Contact</label>
                        <select name="contact_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">None</option>
                            @foreach($contacts ?? [] as $contact)
                                <option value="{{ $contact->id }}" {{ old('contact_id', request('contact_id')) == $contact->id ? 'selected' : '' }}>
                                    {{ $contact->first_name }} {{ $contact->last_name }} ({{ $contact->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Link to Opportunity</label>
                        <select name="opportunity_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">None</option>
                            @foreach($opportunities ?? [] as $opp)
                                <option value="{{ $opp->id }}" {{ old('opportunity_id', request('opportunity_id')) == $opp->id ? 'selected' : '' }}>
                                    {{ $opp->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Load Template</label>
                        <select name="template_id" id="templateSelect" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select template...</option>
                            @foreach($templates ?? [] as $template)
                                <option value="{{ $template->id }}" data-subject="{{ $template->subject }}" data-body="{{ $template->body }}">
                                    {{ $template->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Email Content --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Email Content</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subject <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" id="emailSubject" value="{{ old('subject') }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('subject') border-red-400 @enderror">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Body <span class="text-red-500">*</span></label>
                    <textarea name="body" id="emailBody" rows="12" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('body') border-red-400 @enderror" placeholder="Write your email here...&#10;&#10;Use {{first_name}}, {{last_name}}, {{company}} for personalization.">{{ old('body') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Attachments --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Attachments</h2>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Attach Documents</label>
                <select name="document_ids[]" multiple class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 h-28">
                    @foreach($documents ?? [] as $doc)
                        <option value="{{ $doc->id }}" {{ in_array($doc->id, old('document_ids', [])) ? 'selected' : '' }}>
                            {{ $doc->name }} ({{ $doc->size_kb ? number_format($doc->size_kb) . ' KB' : 'unknown size' }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Hold Ctrl/Cmd to select multiple files</p>
            </div>
        </div>

        {{-- Send Options --}}
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Send Options</h2>
            <div class="space-y-4">
                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="send_mode" value="now" x-model="sendMode" class="w-4 h-4 text-indigo-600">
                        <span class="text-sm font-medium text-slate-700">Send Now</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="send_mode" value="schedule" x-model="sendMode" class="w-4 h-4 text-indigo-600">
                        <span class="text-sm font-medium text-slate-700">Schedule For</span>
                    </label>
                    <div x-show="sendMode === 'schedule'" class="ml-7" x-cloak>
                        <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="send_mode" value="draft" x-model="sendMode" class="w-4 h-4 text-indigo-600">
                        <span class="text-sm font-medium text-slate-700">Save as Draft</span>
                    </label>
                </div>

                {{-- Follow-up --}}
                <div class="pt-4 border-t border-slate-100">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="schedule_followup" x-model="scheduleFollowUp" class="w-4 h-4 text-indigo-600 rounded">
                        <span class="text-sm font-medium text-slate-700">Schedule follow-up if no reply</span>
                    </label>
                    <div x-show="scheduleFollowUp" class="mt-3 ml-7 flex items-center gap-2" x-cloak>
                        <span class="text-sm text-slate-600">Follow up after</span>
                        <input type="number" name="followup_days" value="{{ old('followup_days', 7) }}" min="1" max="90" class="w-20 px-2 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <span class="text-sm text-slate-600">days</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors" x-text="sendMode === 'now' ? 'Send Now' : (sendMode === 'schedule' ? 'Schedule Email' : 'Save Draft')"></button>
            <a href="{{ route('dashboard') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.getElementById('templateSelect')?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            const subjectEl = document.getElementById('emailSubject');
            const bodyEl = document.getElementById('emailBody');
            if (opt.dataset.subject) subjectEl.value = opt.dataset.subject;
            if (opt.dataset.body) bodyEl.value = opt.dataset.body;
        }
    });
</script>
@endpush
@endsection
