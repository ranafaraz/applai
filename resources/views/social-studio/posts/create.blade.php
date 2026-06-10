@extends('layouts.app')
@section('title', 'New Content')
@section('page-title', 'New Content')
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Social Studio', 'url' => route('social-studio.dashboard')],
        ['label' => 'Content', 'url' => route('social-studio.posts.index')],
        ['label' => 'New Post'],
    ]" />
@endsection

@section('content')
<div class="p-6 max-w-5xl space-y-5">
    <div>
        <a href="{{ route('social-studio.posts.index') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Posts</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">New Content</h1>
    </div>

    @include('social-studio.posts._form', [
        'post' => null,
        'assets' => $assets,
        'accounts' => $accounts,
        'action' => route('social-studio.posts.store'),
        'method' => 'POST',
        'submitLabel' => 'Save Draft',
    ])
</div>
@endsection
