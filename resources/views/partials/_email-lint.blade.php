{{-- Pre-send quality checks. Expects $lintIssues: array of
     ['level' => 'danger'|'warning', 'title' => string, 'detail' => string]. --}}
@if(!empty($lintIssues))
    @php
        $hasDanger = collect($lintIssues)->contains(fn ($i) => ($i['level'] ?? '') === 'danger');
        $boxClass  = $hasDanger ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50';
        $headClass = $hasDanger ? 'text-red-800' : 'text-amber-800';
    @endphp
    <div class="border {{ $boxClass }} rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
            <svg class="w-4 h-4 {{ $headClass }} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <p class="text-sm font-semibold {{ $headClass }}">Before you send — {{ count($lintIssues) }} {{ Str::plural('thing', count($lintIssues)) }} to check</p>
        </div>
        <ul class="space-y-2">
            @foreach($lintIssues as $issue)
                @php $isDanger = ($issue['level'] ?? '') === 'danger'; @endphp
                <li class="flex items-start gap-2 text-sm">
                    <span class="mt-0.5 inline-block w-2 h-2 rounded-full flex-shrink-0 {{ $isDanger ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                    <span class="text-slate-700">
                        <strong class="{{ $isDanger ? 'text-red-700' : 'text-amber-700' }}">{{ $issue['title'] }}</strong>
                        — {{ $issue['detail'] }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
