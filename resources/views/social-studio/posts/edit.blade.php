@extends('layouts.app')
@section('title', 'Edit Post')

@section('content')
<div class="p-6 max-w-5xl space-y-5">
    <div>
        <a href="{{ route('social-studio.posts.show', $post->id) }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Post</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Edit Content</h1>
        @if($post->approval_status === 'approved')
            <div class="mt-2 bg-yellow-50 border border-yellow-200 text-yellow-800 text-xs rounded-lg px-3 py-2">
                This post is already approved. Editing will reset approval to pending review.
            </div>
        @endif
    </div>

    @include('social-studio.posts._form', [
        'post' => $post,
        'assets' => $assets,
        'accounts' => $accounts,
        'action' => route('social-studio.posts.update', $post->id),
        'method' => 'PUT',
        'submitLabel' => 'Save Changes',
    ])
</div>
@endsection
