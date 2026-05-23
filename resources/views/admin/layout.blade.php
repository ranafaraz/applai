<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — CRM Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ sidebarOpen: true }">

<div class="flex h-full">
    {{-- Sidebar --}}
    <aside class="w-64 flex-shrink-0 bg-gray-900 text-white flex flex-col">
        <div class="px-5 py-4 border-b border-gray-700 flex items-center gap-2">
            <div class="w-7 h-7 bg-purple-600 rounded flex items-center justify-center text-sm font-bold">A</div>
            <span class="text-sm font-semibold text-white">CRM Admin Panel</span>
        </div>

        <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto text-sm">
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/></svg>
                Dashboard
            </a>
            <a href="{{ route('admin.tenants.index') }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('admin.tenants.*') ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Tenants
            </a>
            <a href="{{ route('admin.users.index') }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('admin.users.index') ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                All Users
            </a>
        </nav>

        <div class="border-t border-gray-700 px-4 py-3">
            <div class="text-xs text-gray-400 mb-2">Logged in as</div>
            <div class="text-sm font-medium text-white">{{ auth()->user()->name }}</div>
            <div class="text-xs text-gray-400">{{ auth()->user()->email }}</div>
            <div class="mt-3 flex gap-2">
                <a href="{{ route('dashboard') }}" class="text-xs text-gray-400 hover:text-white">← CRM</a>
                <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                    @csrf
                    <button class="text-xs text-red-400 hover:text-red-300">Sign out</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center">
            <h1 class="text-base font-semibold text-gray-800">@yield('header', 'Admin')</h1>
            <div class="ml-auto flex items-center gap-3">
                @yield('header-actions')
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            @if (session('success'))
                <div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

</body>
</html>
