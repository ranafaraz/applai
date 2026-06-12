@extends('layouts.app')
@section('title', 'Team — Settings')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Team Management</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $users->count() }} / {{ $userLimit ?? '∞' }} users on your <strong>{{ $tenant->planLabel() }}</strong> plan
            </p>
        </div>
    </div>

    {{-- User limit warning --}}
    @if ($userLimit !== null && $users->count() >= $userLimit)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 text-sm text-yellow-800">
        You've reached your plan's user limit ({{ $userLimit }}). <a href="{{ route('billing.index') }}" class="underline font-medium">Upgrade your plan</a> to add more seats.
    </div>
    @endif

    {{-- Invite user --}}
    @if ($userLimit === null || $users->count() < $userLimit)
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Add Team Member</h2>
        <form method="POST" action="{{ route('team.store') }}" class="grid grid-cols-2 gap-3">
            @csrf
            <input type="text" name="name" placeholder="Full name" required value="{{ old('name') }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="email" name="email" placeholder="Email address" required value="{{ old('email') }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="password" name="password" placeholder="Password (min 8 chars)" required
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="password" name="password_confirmation" placeholder="Confirm password" required
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <div class="flex gap-2">
                <select name="role" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="member">Member — CRM access only</option>
                    <option value="admin">Admin — can manage team</option>
                </select>
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                    Add
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Team members --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 text-sm font-semibold text-gray-700">
            Team Members
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="text-left px-5 py-2">Member</th>
                    <th class="text-left px-5 py-2">Role</th>
                    <th class="text-left px-5 py-2">Status</th>
                    <th class="text-left px-5 py-2">Joined</th>
                    <th class="px-5 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($users as $user)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">
                                {{ $user->initials() }}
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">
                                    {{ $user->name }}
                                    @if ($user->id === auth()->id())
                                        <span class="ml-1 text-xs text-gray-400">(you)</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-400">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        @if ($user->id === auth()->id())
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $user->roleBadge() }}">
                                {{ $user->roleLabel() }}
                            </span>
                        @else
                            <form method="POST" action="{{ route('team.update', $user) }}">
                                @csrf @method('PATCH')
                                <select name="role" onchange="this.form.submit()"
                                        class="border border-gray-200 rounded px-2 py-1 text-xs">
                                    <option value="member" @selected($user->role === 'member')>Member</option>
                                    <option value="admin"  @selected($user->role === 'admin')>Admin</option>
                                </select>
                            </form>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-500">{{ $user->created_at->format('M d, Y') }}</td>
                    <td class="px-5 py-3">
                        @if ($user->id !== auth()->id())
                        <div class="flex items-center justify-end gap-3" x-data="{ open: false }">
                            <button @click="open = !open" class="text-xs text-gray-500 hover:text-gray-700">Reset pw</button>
                            <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" @click.self="open=false">
                                <form method="POST" action="{{ route('team.reset-password', $user) }}"
                                      class="bg-white rounded-xl shadow-xl p-6 w-80 space-y-3" @click.stop>
                                    @csrf @method('PATCH')
                                    <h3 class="text-sm font-semibold">Reset password for {{ $user->name }}</h3>
                                    <input type="password" name="password" placeholder="New password" required minlength="8"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                    <input type="password" name="password_confirmation" placeholder="Confirm"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                    <div class="flex gap-2">
                                        <button class="flex-1 bg-indigo-600 text-white text-sm py-2 rounded-lg">Reset</button>
                                        <button type="button" @click="open=false" class="flex-1 border border-gray-300 text-sm py-2 rounded-lg">Cancel</button>
                                    </div>
                                </form>
                            </div>
                            <form method="POST" action="{{ route('team.destroy', $user) }}"
                                  onsubmit="return confirm('Remove {{ $user->name }} from your team?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:text-red-700">Remove</button>
                            </form>
                        </div>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
