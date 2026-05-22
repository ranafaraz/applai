<div class="text-center py-12">
    <svg class="mx-auto w-10 h-10 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
    </svg>
    <p class="text-sm text-slate-400">{{ $message ?? 'No data found.' }}</p>
    @if(!empty($action_url) && !empty($action_text))
        <a href="{{ $action_url }}" class="mt-3 inline-block text-sm text-indigo-600 hover:underline">{{ $action_text }}</a>
    @endif
</div>
