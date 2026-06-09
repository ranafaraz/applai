@php
    $isEdit = (bool) $post;
    $targets = $post?->targets ?? collect();
    $targetByAccount = $targets->keyBy('social_account_id');
    $defaultSelected = $isEdit ? $targets->pluck('social_account_id')->all() : [];
    $selectedAccounts = collect(old('target_accounts', $defaultSelected))->map(fn ($id) => (int) $id)->all();
    $featuredAssetId = old('featured_asset_id', $post?->mediaAssets?->first(fn ($asset) => (bool) $asset->pivot?->is_featured)?->id);
    $selectedTz = old('timezone_display', $post->timezone_display ?? 'Asia/Karachi');
    $tzList = [
        'UTC' => 'UTC',
        'America/New_York' => 'Eastern Time (US)',
        'America/Chicago' => 'Central Time (US)',
        'America/Denver' => 'Mountain Time (US)',
        'America/Los_Angeles' => 'Pacific Time (US)',
        'Europe/London' => 'London',
        'Europe/Paris' => 'Paris',
        'Asia/Dubai' => 'Dubai',
        'Asia/Karachi' => 'Pakistan',
        'Asia/Kolkata' => 'India',
        'Asia/Singapore' => 'Singapore',
        'Australia/Sydney' => 'Sydney',
    ];
@endphp

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
        <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-5" data-social-post-form>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="bg-white rounded-lg border border-slate-200 p-5 space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Content Workspace</h2>
                <p class="text-xs text-slate-400 mt-0.5">Write once, then customize per selected channel below.</p>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="title_internal" class="block text-xs font-medium text-slate-700 mb-1">Internal Title <span class="text-red-500">*</span></label>
                <input type="text" id="title_internal" name="title_internal" value="{{ old('title_internal', $post->title_internal ?? '') }}" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>

            <div>
                <label for="topic" class="block text-xs font-medium text-slate-700 mb-1">Topic / Theme</label>
                <input type="text" id="topic" name="topic" value="{{ old('topic', $post->topic ?? '') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="post_type" class="block text-xs font-medium text-slate-700 mb-1">Post Type <span class="text-red-500">*</span></label>
                <select name="post_type" id="post_type" required
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    @foreach(['text' => 'Text', 'image' => 'Image', 'article_link' => 'Article / Link'] as $value => $label)
                        <option value="{{ $value }}" {{ old('post_type', $post->post_type ?? 'text') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div id="article_url_field">
                <label for="article_url" class="block text-xs font-medium text-slate-700 mb-1">Article URL</label>
                <input type="url" id="article_url" name="article_url" value="{{ old('article_url', $post->article_url ?? '') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
        </div>

        <div>
            <label for="post_body" class="block text-xs font-medium text-slate-700 mb-1">Content <span class="text-red-500">*</span></label>
            <div class="border border-slate-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500">
                <div class="flex items-center gap-1 px-2 py-1.5 bg-slate-50 border-b border-slate-200">
                    <button type="button" data-editor-command="bold" title="Bold" class="w-8 h-8 rounded hover:bg-slate-200 text-slate-700 text-sm font-bold">B</button>
                    <button type="button" data-editor-command="italic" title="Italic" class="w-8 h-8 rounded hover:bg-slate-200 text-slate-700 text-sm italic">I</button>
                    <button type="button" data-editor-command="underline" title="Underline" class="w-8 h-8 rounded hover:bg-slate-200 text-slate-700 text-sm underline">U</button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <button type="button" data-editor-command="insertUnorderedList" title="Bulleted list" class="w-8 h-8 rounded hover:bg-slate-200 text-slate-700 text-sm">•</button>
                    <button type="button" data-editor-command="insertOrderedList" title="Numbered list" class="w-8 h-8 rounded hover:bg-slate-200 text-slate-700 text-sm">1.</button>
                    <div class="w-px h-5 bg-slate-300 mx-1"></div>
                    <button type="button" data-editor-block="h2" title="Heading" class="px-2 h-8 rounded hover:bg-slate-200 text-slate-700 text-xs font-semibold">H2</button>
                    <button type="button" data-editor-block="p" title="Paragraph" class="px-2 h-8 rounded hover:bg-slate-200 text-slate-700 text-xs">P</button>
                    <button type="button" data-editor-link title="Link" class="px-2 h-8 rounded hover:bg-slate-200 text-slate-700 text-xs">Link</button>
                    <button type="button" data-editor-command="removeFormat" title="Clear formatting" class="ml-auto px-2 h-8 rounded hover:bg-slate-200 text-slate-500 text-xs">Clear</button>
                </div>
                <div id="post_body_editor" contenteditable="true" data-rich-editor
                     class="min-h-[360px] max-h-[620px] overflow-y-auto px-4 py-3 text-sm leading-6 outline-none bg-white prose max-w-none"
                     data-placeholder="Write content for LinkedIn, WordPress, and other connected channels...">{!! old('post_body', $post->post_body ?? '') !!}</div>
            </div>
            <textarea id="post_body" name="post_body" class="hidden">{{ old('post_body', $post->post_body ?? '') }}</textarea>
        </div>

        <div class="grid md:grid-cols-[1fr_auto] gap-3 items-end">
            <div>
                <label for="insert_asset_id" class="block text-xs font-medium text-slate-700 mb-1">Insert Image</label>
                <select id="insert_asset_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    <option value="">Select from media library</option>
                    @foreach($assets as $asset)
                        <option value="{{ $asset->id }}" data-url="{{ $asset->storageUrl() }}" data-alt="{{ $asset->alt_text }}">
                            {{ $asset->caption_or_prompt_note ?: $asset->filename }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="button" data-insert-image
                    class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition">
                Insert
            </button>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="featured_asset_id" class="block text-xs font-medium text-slate-700 mb-1">Featured Image</label>
                <select id="featured_asset_id" name="featured_asset_id"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    <option value="">No featured image</option>
                    @foreach($assets as $asset)
                        <option value="{{ $asset->id }}" {{ (int) $featuredAssetId === $asset->id ? 'selected' : '' }}>
                            {{ $asset->caption_or_prompt_note ?: $asset->filename }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="hashtags" class="block text-xs font-medium text-slate-700 mb-1">Hashtags</label>
                <input type="text" id="hashtags" name="hashtags" value="{{ old('hashtags', $post?->hashtagString() ?? '') }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-5 space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-700">Publish Targets</h2>
            <p class="text-xs text-slate-400 mt-0.5">Select any connected channel. WordPress sites can use their own title, body, slug, status, and featured image.</p>
        </div>

        @forelse($accounts as $account)
            @php
                $target = $targetByAccount->get($account->id);
                $providerKey = $account->provider?->key;
                $meta = old("target_meta.{$account->id}", $target?->platform_metadata_json ?? []);
                $checked = in_array($account->id, $selectedAccounts, true);
            @endphp
            <div class="border border-slate-200 rounded-lg p-4 space-y-3 {{ $checked ? 'ring-1 ring-indigo-200 border-indigo-300' : '' }}" data-target-panel>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="target_accounts[]" value="{{ $account->id }}" data-target-toggle {{ $checked ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold {{ $providerKey === 'linkedin' ? 'bg-blue-100 text-blue-700' : ($providerKey === 'wordpress' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600') }}">
                        {{ $providerKey === 'wordpress' ? 'W' : strtoupper(substr($account->provider?->name ?? 'S', 0, 1)) }}
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-slate-800 truncate">{{ $account->display_name }}</span>
                        <span class="block text-xs text-slate-400">{{ $account->provider?->name }}</span>
                    </span>
                </label>

                <div class="space-y-3 {{ $checked ? '' : 'hidden' }}" data-target-fields>
                    @if($providerKey === 'linkedin')
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Visibility</label>
                                <select name="target_meta[{{ $account->id }}][visibility]"
                                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                    <option value="PUBLIC" {{ ($meta['visibility'] ?? 'PUBLIC') === 'PUBLIC' ? 'selected' : '' }}>Public</option>
                                    <option value="CONNECTIONS" {{ ($meta['visibility'] ?? '') === 'CONNECTIONS' ? 'selected' : '' }}>Connections</option>
                                </select>
                            </div>
                        </div>
                    @endif

                    @if($providerKey === 'wordpress')
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">WordPress Title</label>
                                <input type="text" name="target_meta[{{ $account->id }}][title]"
                                       value="{{ $meta['title'] ?? old('title_internal', $post->title_internal ?? '') }}"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">WordPress Status</label>
                                <select name="target_meta[{{ $account->id }}][wp_status]"
                                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                    <option value="draft" {{ ($meta['wp_status'] ?? 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="publish" {{ ($meta['wp_status'] ?? '') === 'publish' ? 'selected' : '' }}>Publish</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">WordPress Content Override</label>
                            <textarea name="target_meta[{{ $account->id }}][content]" rows="5"
                                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">{{ $target?->platform_body && $target->platform_body !== ($post->post_body ?? '') ? $target->platform_body : '' }}</textarea>
                        </div>

                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Excerpt</label>
                                <input type="text" name="target_meta[{{ $account->id }}][excerpt]" value="{{ $meta['excerpt'] ?? '' }}"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Slug</label>
                                <input type="text" name="target_meta[{{ $account->id }}][slug]" value="{{ $meta['slug'] ?? '' }}"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Featured Image</label>
                                <select name="target_meta[{{ $account->id }}][featured_asset_id]"
                                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                    <option value="">Use post default</option>
                                    @foreach($assets as $asset)
                                        <option value="{{ $asset->id }}" {{ (string)($meta['featured_asset_id'] ?? '') === (string)$asset->id ? 'selected' : '' }}>
                                            {{ $asset->caption_or_prompt_note ?: $asset->filename }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="border border-dashed border-slate-300 rounded-lg p-5 text-sm text-slate-500">
                No connected social accounts found.
                <a href="{{ route('social-studio.connections') }}" class="text-indigo-600 hover:underline">Add a connection</a>.
            </div>
        @endforelse
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-5 space-y-4">
        <h2 class="text-sm font-semibold text-slate-700">Schedule</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="scheduled_at" class="block text-xs font-medium text-slate-700 mb-1">Date &amp; Time</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at"
                       value="{{ old('scheduled_at', $post?->scheduled_at?->copy()->setTimezone($selectedTz)->format('Y-m-d\TH:i')) }}"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
            <div>
                <label for="timezone_display" class="block text-xs font-medium text-slate-700 mb-1">Timezone</label>
                <select id="timezone_display" name="timezone_display"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                    @foreach($tzList as $tz => $label)
                        <option value="{{ $tz }}" {{ $selectedTz === $tz ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <label for="source_notes" class="block text-xs font-medium text-slate-700 mb-1">Source Notes</label>
        <textarea id="source_notes" name="source_notes" rows="3"
                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">{{ old('source_notes', $post->source_notes ?? '') }}</textarea>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition">
            {{ $submitLabel }}
        </button>
        <a href="{{ $post ? route('social-studio.posts.show', $post->id) : route('social-studio.posts.index') }}"
           class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-6 py-2.5 rounded-lg transition">
            Cancel
        </a>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const articleField = document.getElementById('article_url_field');
    const postType = document.getElementById('post_type');
    const editor = document.querySelector('[data-rich-editor]');
    const textarea = document.getElementById('post_body');

    function toggleArticleField() {
        if (articleField && postType) {
            articleField.classList.toggle('hidden', postType.value !== 'article_link');
        }
    }

    postType?.addEventListener('change', toggleArticleField);
    toggleArticleField();

    document.querySelectorAll('[data-target-toggle]').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            const panel = toggle.closest('[data-target-panel]');
            const fields = panel.querySelector('[data-target-fields]');
            fields?.classList.toggle('hidden', !toggle.checked);
            panel.classList.toggle('ring-1', toggle.checked);
            panel.classList.toggle('ring-indigo-200', toggle.checked);
            panel.classList.toggle('border-indigo-300', toggle.checked);
        });
    });

    function syncEditor() {
        if (editor && textarea) {
            textarea.value = editor.innerHTML.trim();
        }
    }

    document.querySelectorAll('[data-editor-command]').forEach(function (button) {
        button.addEventListener('click', function () {
            editor?.focus();
            document.execCommand(button.dataset.editorCommand, false, null);
            syncEditor();
        });
    });

    document.querySelectorAll('[data-editor-block]').forEach(function (button) {
        button.addEventListener('click', function () {
            editor?.focus();
            document.execCommand('formatBlock', false, button.dataset.editorBlock);
            syncEditor();
        });
    });

    document.querySelector('[data-editor-link]')?.addEventListener('click', function () {
        const url = window.prompt('Paste URL');
        if (! url) return;
        editor?.focus();
        document.execCommand('createLink', false, url);
        syncEditor();
    });

    editor?.addEventListener('input', syncEditor);
    editor?.addEventListener('blur', syncEditor);

    document.querySelector('[data-insert-image]')?.addEventListener('click', function () {
        const select = document.getElementById('insert_asset_id');
        const option = select?.selectedOptions[0];

        if (! option || ! option.value) {
            return;
        }

        const url = option.dataset.url;
        const alt = option.dataset.alt || '';
        const html = '<p><img src="' + url + '" data-social-asset-id="' + option.value + '" alt="' + alt.replace(/"/g, '&quot;') + '"></p>';

        editor?.focus();
        document.execCommand('insertHTML', false, html);
        syncEditor();
    });

    document.querySelector('[data-social-post-form]')?.addEventListener('submit', function () {
        syncEditor();
    });

    syncEditor();
});
</script>
@endpush
