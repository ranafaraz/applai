@extends('layouts.guest')

@section('title', 'Verify Email')

@section('content')
<div class="space-y-5 text-center">
    <h2 class="text-lg font-semibold text-slate-800">Verify your email address</h2>

    @if (session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <p class="text-sm text-slate-600">
        Before sending email from your account, please verify your address by clicking the
        link we emailed to <strong>{{ auth()->user()->email }}</strong>. This protects your
        sending reputation and keeps the platform out of spam folders.
    </p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg">
            Resend verification email
        </button>
    </form>

    <p class="text-xs text-slate-400">
        <a href="{{ route('dashboard') }}" class="underline">Back to dashboard</a> — you can keep
        organizing contacts and opportunities while unverified.
    </p>
</div>
@endsection
