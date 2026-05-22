<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Personal Outreach CRM') }} - @yield('title', 'Welcome')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('styles')
</head>
<body class="bg-slate-50 font-sans antialiased">

<div class="min-h-screen flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    {{-- App Logo / Heading --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-600 rounded-2xl shadow-lg mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-900">Personal Outreach CRM</h1>
        <p class="text-sm text-slate-500 mt-1">Manage your outreach pipeline</p>
    </div>

    {{-- Card --}}
    <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        @yield('content')
    </div>

    {{-- Footer --}}
    <p class="mt-8 text-xs text-slate-400">&copy; {{ date('Y') }} Personal Outreach CRM. All rights reserved.</p>
</div>

@stack('scripts')
</body>
</html>
