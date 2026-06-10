@props(['items' => []])

<nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-xs text-slate-400 mb-0.5">
    <a href="{{ route('dashboard') }}" class="hover:text-indigo-600 flex items-center" title="Dashboard">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
    </a>
    @foreach($items as $item)
        <svg class="w-3 h-3 text-slate-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
        @if(!empty($item['url']) && !$loop->last)
            <a href="{{ $item['url'] }}" class="hover:text-indigo-600 truncate max-w-[12rem]">{{ $item['label'] }}</a>
        @else
            <span class="text-slate-600 font-medium truncate max-w-[16rem]">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
