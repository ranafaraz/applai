@extends('layouts.app')

@section('title', 'Email Accounts')
@section('page-title', 'Email Accounts')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">Manage your SMTP/IMAP sending and receiving accounts.</p>
    <a href="{{ route('email-accounts.create') }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Account
    </a>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">SMTP</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">IMAP</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Daily Usage</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Sync</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($accounts ?? [] as $account)
                    @php
                        $usagePct = $account->daily_limit > 0 ? min(100, round(($account->emails_sent_today / $account->daily_limit) * 100)) : 0;
                        $barColor = $usagePct >= 90 ? 'bg-red-500' : ($usagePct >= 70 ? 'bg-yellow-500' : 'bg-green-500');
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3.5 font-medium text-slate-800">{{ $account->name }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $account->email }}</td>
                        <td class="px-5 py-3.5">
                            @if($account->smtp_status === 'ok')
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> Connected
                                </span>
                            @elseif($account->smtp_status === 'error')
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span> Error
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span> Unknown
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            @if($account->imap_status === 'ok')
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> Connected
                                </span>
                            @elseif($account->imap_status === 'error')
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span> Error
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span> Unknown
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 w-44">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-slate-200 rounded-full h-2">
                                    <div class="{{ $barColor }} h-2 rounded-full" style="width: {{ $usagePct }}%"></div>
                                </div>
                                <span class="text-xs text-slate-500 whitespace-nowrap">{{ $account->emails_sent_today ?? 0 }}/{{ $account->daily_limit ?? 0 }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-500">
                            {{ $account->last_sync_at ? $account->last_sync_at->diffForHumans() : 'Never' }}
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('email-accounts.edit', $account) }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                                <button class="text-xs text-slate-600 hover:text-slate-800 font-medium">Sync</button>
                                <form method="POST" action="{{ route('email-accounts.destroy', $account) }}" onsubmit="return confirm('Delete this email account?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                                <p class="text-slate-500 text-sm font-medium">No email accounts yet</p>
                                <a href="{{ route('email-accounts.create') }}" class="text-indigo-600 text-sm hover:underline">Add your first account</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
