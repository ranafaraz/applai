@extends('layouts.app')
@section('title', 'Opportunity Imports')
@section('page-title', 'Opportunity Imports')
@section('content')
<div>
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">Import opportunities from CSV files</p>
        <a href="{{ route('opportunity-imports.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Import CSV
        </a>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        @if($imports->isEmpty())
            <div class="text-center py-16">
                <p class="text-slate-500 font-medium">No imports yet.</p>
                <a href="{{ route('opportunity-imports.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Upload your first CSV</a>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">File</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Progress</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Results</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($imports as $import)
                    @php
                        $statusColors = ['pending'=>'yellow','parsing'=>'blue','parsed'=>'blue','processing'=>'blue','completed'=>'green','failed'=>'red'];
                        $sc = $statusColors[$import->status] ?? 'gray';
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-800 font-medium">{{ $import->file_name }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($import->status) }}</span></td>
                        <td class="px-4 py-3 w-36">
                            <div class="w-full bg-slate-200 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $import->progress_percent }}%"></div>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $import->processed_rows }}/{{ $import->total_rows }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <span class="text-green-600 font-medium">{{ $import->imported_rows }} imported</span> &bull;
                            <span class="text-yellow-600">{{ $import->skipped_rows }} skipped</span> &bull;
                            <span class="text-red-500">{{ $import->failed_rows }} failed</span>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $import->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('opportunity-imports.show', $import) }}" class="text-xs text-indigo-600 hover:underline px-2 py-1 rounded hover:bg-slate-100">Details</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($imports->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">{{ $imports->withQueryString()->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
