@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<form method="POST" action="{{ route('login') }}" class="space-y-5">
    @csrf

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Email --}}
    <div>
        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
        <input
            id="email"
            type="email"
            name="email"
            value="{{ old('email') }}"
            required
            autofocus
            autocomplete="email"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-400 @enderror"
            placeholder="you@example.com"
        >
    </div>

    {{-- Password --}}
    <div>
        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
        <input
            id="password"
            type="password"
            name="password"
            required
            autocomplete="current-password"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('password') border-red-400 @enderror"
            placeholder="••••••••"
        >
    </div>

    {{-- Remember Me --}}
    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
            <input type="checkbox" name="remember" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
            Remember me
        </label>
        @if(Route::has('password.request'))
            <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Forgot password?</a>
        @endif
    </div>

    {{-- Demo Credentials --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
        <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Demo Credentials</p>
        <p class="text-sm text-blue-800">Email: <span class="font-mono font-medium">demo@example.com</span></p>
        <p class="text-sm text-blue-800">Password: <span class="font-mono font-medium">password</span></p>
    </div>

    {{-- Submit --}}
    <button
        type="submit"
        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 px-4 rounded-lg transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
    >
        Sign In
    </button>

    {{-- Register Link --}}
    @if(Route::has('register'))
        <p class="text-center text-sm text-slate-500">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Create one</a>
        </p>
    @endif
</form>
@endsection
