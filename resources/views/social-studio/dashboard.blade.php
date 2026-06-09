@extends('layouts.app')
@section('title', 'Social Studio')

@section('content')
<div class="p-6 space-y-6">

    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Social Studio</h1>
            <p class="text-sm text-slate-500 mt-1">Plan, publish, and review content across connected channels.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('social-studio.connections') }}"
               class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition">
                Manage Connections
            </a>
            <a href="{{ route('social-studio.posts.create') }}"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Content
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Connected Channels</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $connectedCount }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Drafts / Review</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $draftsCount }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Scheduled 7d</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1">{{ $scheduledCount }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Failed</p>
            <p class="text-3xl font-bold {{ $failedCount > 0 ? 'text-red-600' : 'text-slate-800' }} mt-1">{{ $failedCount }}</p>
        </div>
    </div>

    <div class="grid xl:grid-cols-[1.1fr_0.9fr] gap-5">
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Connections</h2>
                <a href="{{ route('social-studio.connections') }}" class="text-xs text-indigo-600 hover:underline">Configure</a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($connectedAccounts as $account)
                    <div class="px-5 py-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold
                                {{ $account->provider?->key === 'linkedin' ? 'bg-blue-100 text-blue-700' : ($account->provider?->key === 'wordpress' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600') }}">
                                {{ $account->provider?->key === 'wordpress' ? 'W' : strtoupper(substr($account->provider?->name ?? 'S', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate">{{ $account->display_name ?: $account->provider?->name }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $account->provider?->name }} @if($account->public_profile_url) · {{ $account->public_profile_url }} @endif</p>
                            </div>
                        </div>
                        <span class="text-xs rounded-full px-2 py-1 font-medium
                            {{ $account->status === 'connected' ? 'bg-green-100 text-green-700' : ($account->status === 'reauthorization_required' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                            {{ str_replace('_', ' ', ucfirst($account->status)) }}
                        </span>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-slate-500">No social channels connected yet.</p>
                        <a href="{{ route('social-studio.connections') }}" class="mt-3 inline-flex text-sm text-indigo-600 hover:underline">Add a connection</a>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Recent Publishing</h2>
                <a href="{{ route('social-studio.published') }}" class="text-xs text-indigo-600 hover:underline">Published</a>
            </div>

            @if($lastPublished)
                <div class="px-5 py-4 border-b border-slate-100">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Published</p>
                    <p class="text-sm font-medium text-slate-800 mt-1">{{ $lastPublished->post->title_internal }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $lastPublished->published_at?->diffForHumans() }} via {{ ucfirst($lastPublished->provider_key) }}
                    </p>
                </div>
            @endif

            <div class="divide-y divide-slate-100">
                @forelse($recentPublished as $post)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate">{{ $post->title_internal }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">Updated {{ $post->updated_at->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('social-studio.posts.show', $post->id) }}" class="text-xs text-indigo-600 hover:underline">View</a>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No published content yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-sm font-semibold text-slate-700">Platform Availability</h2>
            <a href="{{ route('social-studio.insights') }}" class="text-xs text-indigo-600 hover:underline">View analytics</a>
        </div>
        <div class="grid md:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($providers as $provider)
                <div class="border border-slate-200 rounded-lg p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-medium text-slate-800">{{ $provider->name }}</p>
                        <span class="text-[11px] rounded-full px-2 py-0.5 font-semibold
                            {{ $provider->status === 'enabled' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ str_replace('_', ' ', ucfirst($provider->status)) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ $connectionGroups->get($provider->key, collect())->where('status', 'connected')->count() }} connected
                    </p>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
