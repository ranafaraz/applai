@extends('layouts.app')
@section('title', 'Compose Email')
@section('page-title', 'Compose Email')
@section('content')
<div class="max-w-3xl" x-data="{
    sendOption: 'now',
    templateId: '',
    loadTemplate() {
        if (!this.templateId) return;
        fetch('/emails/template/' + this.templateId)
            .then(r => r.json())
            .then(data => {
                document.getElementById('subject').value = data.subject || '';
                document.getElementById('body').value = data.body || '';
            });
    }
}">
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ route('emails.store') }}" class="space-y-4">
            @csrf
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- From Account --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">From Account <span class="text-red-500">*</span></label>
                @php
                    $defaultAccountId = old('email_account_id')
                        ?: request('account_id')
                        ?: optional($emailAccounts->firstWhere('is_default', true))->id;
                @endphp
                <select name="email_account_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select sending account...</option>
                    @foreach($emailAccounts as $account)
                        <option value="{{ $account->id }}" {{ $defaultAccountId == $account->id ? 'selected' : '' }}>
                            {{ $account->from_name }} &lt;{{ $account->email }}&gt;@if($account->is_default) ★ default @endif
                        </option>
                    @endforeach
                </select>
                @if($emailAccounts->isEmpty())
                    <p class="text-xs text-red-500 mt-1">No email accounts configured. <a href="{{ route('email-accounts.create') }}" class="underline">Add one first</a>.</p>
                @endif
            </div>

            {{-- To --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">To Email <span class="text-red-500">*</span></label>
                    <input type="email" name="to_email" value="{{ old('to_email') }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="recipient@example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">To Name</label>
                    <input type="text" name="to_name" value="{{ old('to_name') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Recipient Name">
                </div>
            </div>

            {{-- Link to Contact / Opportunity --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Link to Contact</label>
                    <select name="contact_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">None</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}" {{ (old('contact_id') == $contact->id || request('contact_id') == $contact->id) ? 'selected' : '' }}>
                                {{ $contact->full_name }} ({{ $contact->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Link to Opportunity</label>
                    <select name="opportunity_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">None</option>
                        @foreach($opportunities as $opp)
                            <option value="{{ $opp->id }}" {{ (old('opportunity_id') == $opp->id || request('opportunity_id') == $opp->id) ? 'selected' : '' }}>
                                {{ Str::limit($opp->title, 60) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Template --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Load Template</label>
                <select x-model="templateId" @change="loadTemplate()" name="template_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select template (optional)...</option>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Subject --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Subject <span class="text-red-500">*</span></label>
                <input id="subject" type="text" name="subject" value="{{ old('subject') }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Email subject...">
            </div>

            {{-- Body --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Body <span class="text-red-500">*</span></label>
                <textarea id="body" name="body" rows="12" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Write your email here...">{{ old('body') }}</textarea>
            </div>

            {{-- CC / BCC --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">CC</label>
                    <input type="text" name="cc" value="{{ old('cc') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="cc@example.com, ...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">BCC</label>
                    <input type="text" name="bcc" value="{{ old('bcc') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="bcc@example.com, ...">
                </div>
            </div>

            {{-- Send Options --}}
            <div class="bg-slate-50 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-slate-700">Send Options</p>
                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="now" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">Send Now</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="schedule" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">Schedule For Later</span>
                    </label>
                    <div x-show="sendOption === 'schedule'" x-cloak class="pl-6">
                        <input type="datetime-local" name="scheduled_at" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="send_option" value="draft" x-model="sendOption" class="text-indigo-600">
                        <span class="text-sm text-slate-700">Save as Draft</span>
                    </label>
                </div>
            </div>

            {{-- Follow-up --}}
            <div x-data="{ followUp: false }" class="bg-slate-50 rounded-xl p-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="schedule_follow_up" x-model="followUp" value="1" class="text-indigo-600">
                    <span class="text-sm font-medium text-slate-700">Schedule follow-up if no reply received</span>
                </label>
                <div x-show="followUp" x-cloak class="mt-3 flex items-center gap-2">
                    <label class="text-sm text-slate-600">After</label>
                    <input type="number" name="follow_up_days" value="5" min="1" max="30" class="w-16 px-2 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <label class="text-sm text-slate-600">days</label>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    <span x-text="sendOption === 'now' ? 'Send Now' : (sendOption === 'schedule' ? 'Schedule Email' : 'Save Draft')"></span>
                </button>
                <a href="{{ route('emails.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
