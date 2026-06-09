@extends('layouts.app')
@section('title', 'Social Connections')

@section('content')
<div class="p-6 space-y-6 max-w-6xl" data-connections-page>

    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Connections</h1>
            <p class="text-sm text-slate-500 mt-1">Connect channels once, then use them as publish targets in Content.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('social-studio.oauth-apps.index') }}"
               class="inline-flex items-center text-sm text-slate-700 hover:text-slate-900 border border-slate-300 hover:border-slate-400 bg-white hover:bg-slate-50 px-3 py-2 rounded-lg transition">
                LinkedIn Apps
            </a>
            <button type="button" data-open-wp-modal
                    class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                Add WordPress Site
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">{{ session('error') }}</div>
    @endif

    @php
        $linkedInApps = $oauthApps->where('provider_key', 'linkedin');
        $wordpressAccounts = $accounts->filter(fn ($account) => $account->provider?->key === 'wordpress');
        $otherProviders = $providers->whereNotIn('key', ['linkedin', 'wordpress']);
    @endphp

    <div class="grid xl:grid-cols-2 gap-5">
        <section class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">LinkedIn</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Profiles connected through your LinkedIn developer apps.</p>
                </div>
                @if($linkedInApps->isNotEmpty())
                    <a href="{{ route('social-studio.connections.connect', ['app_id' => $linkedInApps->first()->id]) }}"
                       class="text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded-lg transition">Add Profile</a>
                @endif
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($linkedInApps as $app)
                    <div class="px-5 py-4 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $app->label }}</p>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $app->client_id }}</p>
                            </div>
                            @if($app->is_default)
                                <span class="text-[11px] font-semibold bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5">Default App</span>
                            @endif
                        </div>

                        @forelse($app->accounts as $account)
                            @include('social-studio.partials.connection-row', ['account' => $account])
                        @empty
                            <div class="border border-dashed border-slate-300 rounded-lg p-4 text-sm text-slate-500">
                                No profiles connected for this app.
                            </div>
                        @endforelse
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-slate-500">No LinkedIn app configured yet.</p>
                        <a href="{{ route('social-studio.oauth-apps.create') }}"
                           class="mt-3 inline-flex bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                            Add LinkedIn App
                        </a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">WordPress</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Sites connected with WordPress application passwords.</p>
                </div>
                <button type="button" data-open-wp-modal
                        class="text-xs bg-slate-900 hover:bg-slate-800 text-white font-medium px-3 py-1.5 rounded-lg transition">
                    Add Site
                </button>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($wordpressAccounts as $account)
                    <div class="px-5 py-4">
                        @include('social-studio.partials.connection-row', ['account' => $account])
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-slate-500">No WordPress sites connected yet.</p>
                        <button type="button" data-open-wp-modal class="mt-3 inline-flex text-sm text-indigo-600 hover:underline">Connect a site</button>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="bg-white rounded-lg border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-800 mb-3">Other Channels</h2>
        <div class="grid md:grid-cols-3 lg:grid-cols-5 gap-3">
            @foreach($otherProviders as $provider)
                <div class="border border-slate-200 rounded-lg p-3 {{ $provider->status === 'enabled' ? '' : 'opacity-60' }}">
                    <p class="text-sm font-medium text-slate-800">{{ $provider->name }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ str_replace('_', ' ', ucfirst($provider->status)) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <div data-wp-modal class="hidden fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-900/50" data-close-wp-modal></div>
        <div class="relative mx-auto mt-16 w-full max-w-lg px-4">
            <form method="POST" action="{{ route('social-studio.connections.wordpress.store') }}"
                  class="bg-white rounded-lg shadow-xl border border-slate-200">
                @csrf
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Connect WordPress Site</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Use a WordPress application password from the site user profile.</p>
                    </div>
                    <button type="button" data-close-wp-modal class="p-1.5 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <label for="wp_site_url" class="block text-xs font-medium text-slate-700 mb-1">Site URL <span class="text-red-500">*</span></label>
                        <input type="url" id="wp_site_url" name="site_url" value="{{ old('site_url') }}" required placeholder="https://blog.example.com"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                    <div>
                        <label for="wp_label" class="block text-xs font-medium text-slate-700 mb-1">Display Name</label>
                        <input type="text" id="wp_label" name="label" value="{{ old('label') }}" placeholder="Company Blog"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label for="wp_username" class="block text-xs font-medium text-slate-700 mb-1">Username <span class="text-red-500">*</span></label>
                            <input type="text" id="wp_username" name="username" value="{{ old('username') }}" required
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                        <div>
                            <label for="wp_application_password" class="block text-xs font-medium text-slate-700 mb-1">Application Password <span class="text-red-500">*</span></label>
                            <input type="password" id="wp_application_password" name="application_password" required
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 px-5 py-4 border-t border-slate-100 bg-slate-50">
                    <button type="button" data-close-wp-modal class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                        Connect Site
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('[data-wp-modal]');
    const openButtons = document.querySelectorAll('[data-open-wp-modal]');
    const closeButtons = document.querySelectorAll('[data-close-wp-modal]');

    function openModal() {
        modal?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        modal?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    openButtons.forEach(button => button.addEventListener('click', openModal));
    closeButtons.forEach(button => button.addEventListener('click', closeModal));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeModal();
    });

    @if($errors->has('site_url') || $errors->has('username') || $errors->has('application_password'))
        openModal();
    @endif
});
</script>
@endpush
@endsection
