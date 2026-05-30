@extends('layouts.app')
@section('title', 'New Draft')

@section('content')
<div class="p-6 max-w-3xl space-y-5" x-data="createPost()">

    <div>
        <a href="{{ route('social-studio.posts.index') }}" class="text-xs text-slate-500 hover:text-slate-700">&larr; Back to Posts</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">New Draft</h1>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
            <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('social-studio.posts.store') }}" class="space-y-5">
        @csrf

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-700">Post Details</h2>

            <div>
                <label for="title_internal" class="block text-xs font-medium text-slate-700 mb-1">Internal Title <span class="text-red-500">*</span></label>
                <input type="text" id="title_internal" name="title_internal" value="{{ old('title_internal') }}" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="e.g. LinkedIn post about Q2 results">
            </div>

            <div>
                <label for="topic" class="block text-xs font-medium text-slate-700 mb-1">Topic / Theme</label>
                <input type="text" id="topic" name="topic" value="{{ old('topic') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="e.g. Thought leadership, Product launch">
            </div>

            <div>
                <label for="post_type" class="block text-xs font-medium text-slate-700 mb-1">Post Type <span class="text-red-500">*</span></label>
                <select name="post_type" id="post_type" required
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        onchange="togglePostTypeFields()">
                    <option value="text" {{ old('post_type','text') === 'text' ? 'selected' : '' }}>Text Only</option>
                    <option value="image" {{ old('post_type') === 'image' ? 'selected' : '' }}>Image</option>
                    <option value="article_link" {{ old('post_type') === 'article_link' ? 'selected' : '' }}>Article / Link</option>
                </select>
            </div>

            <div id="article_url_field" class="hidden">
                <label for="article_url" class="block text-xs font-medium text-slate-700 mb-1">Article URL <span class="text-red-500">*</span></label>
                <input type="url" id="article_url" name="article_url" value="{{ old('article_url') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="https://example.com/article">
            </div>

            {{-- Image field with AJAX upload modal --}}
            <div id="image_field" class="hidden" x-data="mediaModal()">
                <label for="featured_asset_id" class="block text-xs font-medium text-slate-700 mb-1">Featured Image (from Media Library)</label>

                <div class="flex items-center gap-2">
                    <select id="featured_asset_id" name="featured_asset_id" x-ref="assetSelect"
                            class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <option value="">-- Select image --</option>
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}" {{ old('featured_asset_id') == $asset->id ? 'selected' : '' }}>
                                {{ $asset->caption_or_prompt_note ?: $asset->filename }} ({{ $asset->alt_text }})
                            </option>
                        @endforeach
                        {{-- New options added via AJAX appear here --}}
                        <template x-for="a in newAssets" :key="a.id">
                            <option :value="a.id" x-text="a.label" :selected="a.id === selectedNewAsset"></option>
                        </template>
                    </select>
                    <button type="button" @click="open = true"
                            class="flex-shrink-0 inline-flex items-center gap-1.5 border border-slate-300 hover:border-indigo-400 text-slate-600 hover:text-indigo-700 text-xs font-medium px-3 py-2 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Upload New
                    </button>
                </div>

                {{-- Upload Modal --}}
                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @keydown.escape.window="open = false">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4" @click.stop>
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-800">Upload New Image</h3>
                            <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label for="modal-file" class="block text-xs font-medium text-slate-700 mb-1">Image File <span class="text-red-500">*</span></label>
                                <input type="file" id="modal-file" x-ref="fileInput" accept="image/jpeg,image/png,image/gif,image/webp"
                                       class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            </div>
                            <div>
                                <label for="modal-alt" class="block text-xs font-medium text-slate-700 mb-1">Alt Text <span class="text-red-500">*</span></label>
                                <input type="text" id="modal-alt" x-ref="altInput" placeholder="Describe the image for accessibility"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            <div>
                                <label for="modal-title" class="block text-xs font-medium text-slate-700 mb-1">Title (optional)</label>
                                <input type="text" id="modal-title" x-ref="titleInput" placeholder="e.g. Product screenshot"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            <div x-show="uploadError" class="text-xs text-red-600" x-text="uploadError"></div>
                        </div>

                        <div class="flex gap-3 justify-end">
                            <button type="button" @click="open = false"
                                    class="text-sm text-slate-600 hover:text-slate-800 px-4 py-2 rounded-lg border border-slate-200 hover:border-slate-300">
                                Cancel
                            </button>
                            <button type="button" @click="uploadImage()" :disabled="uploading"
                                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                                <span x-show="uploading">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </span>
                                <span x-text="uploading ? 'Uploading…' : 'Upload & Select'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Post body with formatting toolbar --}}
            <div>
                <label for="post_body" class="block text-xs font-medium text-slate-700 mb-1">Post Body <span class="text-red-500">*</span></label>
                <div class="border border-slate-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500">
                    {{-- Formatting toolbar --}}
                    <div class="flex items-center gap-1 px-2 py-1.5 bg-slate-50 border-b border-slate-200">
                        <button type="button" title="Bold" onclick="insertFormatting('**', '**')"
                                class="p-1.5 rounded hover:bg-slate-200 text-slate-600 text-xs font-bold">B</button>
                        <button type="button" title="Italic" onclick="insertFormatting('_', '_')"
                                class="p-1.5 rounded hover:bg-slate-200 text-slate-600 text-xs italic">I</button>
                        <div class="w-px h-4 bg-slate-300 mx-1"></div>
                        <button type="button" title="Insert line break" onclick="insertText('\n')"
                                class="p-1.5 rounded hover:bg-slate-200 text-slate-500 text-xs">↵</button>
                        <div class="w-px h-4 bg-slate-300 mx-1"></div>
                        {{-- Common emojis --}}
                        @foreach(['🚀','💡','✅','🔥','👉','📌','🎯','💼','🤝','📈'] as $emoji)
                        <button type="button" onclick="insertText('{{ $emoji }}')"
                                class="p-1 rounded hover:bg-slate-200 text-sm leading-none">{{ $emoji }}</button>
                        @endforeach
                        <div class="ml-auto text-xs text-slate-400">
                            <span id="char-count">0</span>/3000
                        </div>
                    </div>
                    <textarea id="post_body" name="post_body" rows="8" required maxlength="3000"
                              class="w-full px-3 py-2 text-sm outline-none resize-none"
                              placeholder="Write your LinkedIn post here..."
                              oninput="document.getElementById('char-count').textContent=this.value.length">{{ old('post_body') }}</textarea>
                </div>
                <p class="text-xs text-slate-400 mt-1">Hashtags are added separately below.</p>
            </div>

            <div>
                <label for="hashtags" class="block text-xs font-medium text-slate-700 mb-1">Hashtags</label>
                <input type="text" id="hashtags" name="hashtags" value="{{ old('hashtags') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                       placeholder="#leadership #linkedin #productivity">
                <p class="text-xs text-slate-400 mt-1">Space or comma separated. The # symbol is optional.</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-700">Schedule (Optional)</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="scheduled_at" class="block text-xs font-medium text-slate-700 mb-1">Date &amp; Time</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at') }}"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label for="timezone_display" class="block text-xs font-medium text-slate-700 mb-1">Timezone</label>
                    <select id="timezone_display" name="timezone_display"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        @php
                        $tzList = [
                            'UTC'                    => 'UTC',
                            'America/New_York'       => 'Eastern Time (US)',
                            'America/Chicago'        => 'Central Time (US)',
                            'America/Denver'         => 'Mountain Time (US)',
                            'America/Los_Angeles'    => 'Pacific Time (US)',
                            'America/Sao_Paulo'      => 'Brasília Time',
                            'Europe/London'          => 'London (GMT/BST)',
                            'Europe/Paris'           => 'Central European Time',
                            'Europe/Berlin'          => 'Berlin (CET/CEST)',
                            'Europe/Moscow'          => 'Moscow Time',
                            'Africa/Cairo'           => 'Cairo (EET)',
                            'Asia/Dubai'             => 'Dubai (GST, UTC+4)',
                            'Asia/Karachi'           => 'Pakistan (PKT, UTC+5)',
                            'Asia/Kolkata'           => 'India (IST, UTC+5:30)',
                            'Asia/Dhaka'             => 'Bangladesh (BST, UTC+6)',
                            'Asia/Bangkok'           => 'Bangkok (ICT, UTC+7)',
                            'Asia/Singapore'         => 'Singapore (SGT, UTC+8)',
                            'Asia/Shanghai'          => 'China (CST, UTC+8)',
                            'Asia/Tokyo'             => 'Japan (JST, UTC+9)',
                            'Australia/Sydney'       => 'Sydney (AEST/AEDT)',
                            'Pacific/Auckland'       => 'New Zealand (NZST)',
                        ];
                        $selectedTz = old('timezone_display', 'Asia/Karachi');
                        @endphp
                        @foreach($tzList as $tz => $label)
                            <option value="{{ $tz }}" {{ $selectedTz === $tz ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="text-xs text-slate-400">Scheduling a post does NOT publish it. You must approve it first.</p>
        </div>

        @if($linkedInAccount)
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
            <h2 class="text-sm font-semibold text-slate-700">LinkedIn Settings</h2>
            <div>
                <label for="visibility" class="block text-xs font-medium text-slate-700 mb-1">Visibility</label>
                <select id="visibility" name="visibility" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    <option value="PUBLIC" {{ old('visibility','PUBLIC') === 'PUBLIC' ? 'selected' : '' }}>Public (anyone)</option>
                    <option value="CONNECTIONS" {{ old('visibility') === 'CONNECTIONS' ? 'selected' : '' }}>Connections only</option>
                </select>
            </div>
        </div>
        @endif

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <label for="source_notes" class="block text-xs font-medium text-slate-700 mb-1">Source Notes (Internal)</label>
            <textarea id="source_notes" name="source_notes" rows="3"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                      placeholder="Research links, references, context...">{{ old('source_notes') }}</textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Save Draft
            </button>
            <a href="{{ route('social-studio.posts.index') }}"
               class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-6 py-2.5 rounded-lg transition">
                Cancel
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function createPost() {
    return {};
}

function mediaModal() {
    return {
        open: false,
        uploading: false,
        uploadError: '',
        newAssets: [],
        selectedNewAsset: null,

        async uploadImage() {
            this.uploadError = '';
            const file = this.$refs.fileInput.files[0];
            const alt  = this.$refs.altInput.value.trim();

            if (! file) { this.uploadError = 'Please select an image file.'; return; }
            if (! alt)  { this.uploadError = 'Alt text is required.'; return; }

            this.uploading = true;

            const form = new FormData();
            form.append('file', file);
            form.append('alt_text', alt);
            form.append('title', this.$refs.titleInput.value.trim());
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const res = await fetch('{{ route('social-studio.media.store') }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: form,
                });

                if (! res.ok) {
                    const err = await res.json().catch(() => ({}));
                    this.uploadError = err.message || 'Upload failed (' + res.status + ').';
                    return;
                }

                const data = await res.json();
                this.newAssets.push(data);
                this.selectedNewAsset = data.id;

                // Also update the native <select> directly so the form submits correctly
                const select = this.$refs.assetSelect;
                const opt = new Option(data.label, data.id, true, true);
                select.appendChild(opt);
                select.value = data.id;

                this.open = false;
                this.$refs.fileInput.value = '';
                this.$refs.altInput.value  = '';
                this.$refs.titleInput.value = '';
            } catch (e) {
                this.uploadError = 'Network error. Please try again.';
            } finally {
                this.uploading = false;
            }
        }
    };
}

function togglePostTypeFields() {
    const type = document.getElementById('post_type').value;
    document.getElementById('article_url_field').classList.toggle('hidden', type !== 'article_link');
    document.getElementById('image_field').classList.toggle('hidden', type !== 'image');
}

function insertFormatting(before, after) {
    const ta = document.getElementById('post_body');
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    const sel   = ta.value.substring(start, end);
    ta.value = ta.value.substring(0, start) + before + sel + after + ta.value.substring(end);
    ta.selectionStart = start + before.length;
    ta.selectionEnd   = start + before.length + sel.length;
    ta.focus();
    document.getElementById('char-count').textContent = ta.value.length;
}

function insertText(text) {
    const ta  = document.getElementById('post_body');
    const pos = ta.selectionStart;
    ta.value  = ta.value.substring(0, pos) + text + ta.value.substring(pos);
    ta.selectionStart = ta.selectionEnd = pos + text.length;
    ta.focus();
    document.getElementById('char-count').textContent = ta.value.length;
}

document.addEventListener('DOMContentLoaded', function () {
    togglePostTypeFields();
    const ta = document.getElementById('post_body');
    if (ta) document.getElementById('char-count').textContent = ta.value.length;
});
</script>
@endpush
@endsection
