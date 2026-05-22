@extends('layouts.app')
@section('title', 'Settings')
@section('page-title', 'Settings')
@section('content')
<div class="max-w-2xl">
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
            @csrf @method('PUT')
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Timezone</label>
                <select name="timezone" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach(timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" {{ ($setting->timezone ?? 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date Format</label>
                <select name="date_format" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach(['Y-m-d'=>'2024-01-31','m/d/Y'=>'01/31/2024','d/m/Y'=>'31/01/2024','M j, Y'=>'Jan 31, 2024'] as $fmt=>$example)
                        <option value="{{ $fmt }}" {{ ($setting->date_format ?? 'Y-m-d') === $fmt ? 'selected' : '' }}>{{ $fmt }} ({{ $example }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Default Follow-up Delay (days)</label>
                <input type="number" name="default_follow_up_days" value="{{ $setting->default_follow_up_days ?? 5 }}" min="1" max="30" class="w-32 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-slate-400 mt-1">How many days to wait before sending a follow-up email.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Default Sending Account</label>
                <select name="default_email_account_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">No default</option>
                    @foreach($emailAccounts as $account)
                        <option value="{{ $account->id }}" {{ ($setting->default_email_account_id ?? null) == $account->id ? 'selected' : '' }}>{{ $account->name }} ({{ $account->email }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-2">
                <p class="text-sm font-medium text-slate-700">Notifications</p>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notify_on_reply" value="1" {{ ($setting->notify_on_reply ?? true) ? 'checked' : '' }} class="text-indigo-600 rounded">
                    <span class="text-sm text-slate-600">Notify when a reply is received</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notify_on_bounce" value="1" {{ ($setting->notify_on_bounce ?? true) ? 'checked' : '' }} class="text-indigo-600 rounded">
                    <span class="text-sm text-slate-600">Notify when an email bounces</span>
                </label>
            </div>

            <div class="pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg">Save Settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
