@extends('layouts.app')
@section('title', 'Import Contacts')
@section('page-title', 'Import Contacts from CSV')
@section('content')
<div class="max-w-xl">
    <div class="mb-4"><a href="{{ route('imports.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Imports</a></div>
    <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-800">
            <p class="font-semibold mb-1">CSV Format</p>
            <p>Your CSV should include columns like: <code class="font-mono bg-blue-100 px-1 rounded">first_name</code>, <code class="font-mono bg-blue-100 px-1 rounded">last_name</code>, <code class="font-mono bg-blue-100 px-1 rounded">email</code>, <code class="font-mono bg-blue-100 px-1 rounded">company</code>, <code class="font-mono bg-blue-100 px-1 rounded">phone</code>, <code class="font-mono bg-blue-100 px-1 rounded">job_title</code></p>
            <p class="mt-1">Column headers are auto-detected. The <code class="font-mono bg-blue-100 px-1 rounded">email</code> column is required.</p>
        </div>
        <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">CSV File <span class="text-red-500">*</span></label>
                <input type="file" name="csv_file" accept=".csv,text/csv" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-slate-400 mt-1">Max 10MB. CSV format required.</p>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg">Upload & Import</button>
                <a href="{{ route('imports.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
