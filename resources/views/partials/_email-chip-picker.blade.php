{{--
    Multi-select chip picker for email addresses. Suggests contact emails
    from a passed-in list, but also accepts arbitrary email addresses
    typed by the user (press Enter or , or ; to commit).

    Renders selected emails as removable chips. Submits each address as
    <input type="hidden" name="{name}[]" value="..."> so the controller
    receives an array — StoreEmailMessageRequest::prepareForValidation()
    already normalises array-valued cc/bcc.

    Required slot variables:
      $name      string  form field name (e.g. 'cc', 'bcc')
      $contacts  Collection|array  of {email, full_name}
      $label     string  display label
      $selected  array|string|null  pre-selected emails (string CSV, array of strings, or null)
--}}
@php
    if (! isset($selected)) $selected = [];
    if (is_string($selected)) {
        $selected = array_values(array_filter(array_map('trim', preg_split('/[;,\s]+/', $selected) ?: [])));
    } elseif (! is_array($selected)) {
        $selected = [];
    }
    // Normalise contacts to JS-friendly shape
    $jsContacts = collect($contacts ?? [])->map(fn ($c) => [
        'email' => is_array($c) ? ($c['email'] ?? '') : $c->email,
        'label' => is_array($c)
            ? ($c['full_name'] ?? $c['name'] ?? $c['email'] ?? '')
            : (trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: $c->email),
    ])->filter(fn ($c) => $c['email'])->values();
    $pickerId = $name . 'Picker';
@endphp

<div x-data="emailChipPicker({{ $jsContacts->toJson() }}, {{ collect($selected)->toJson() }}, '{{ $name }}')" class="space-y-1">
    <div class="border border-slate-300 rounded-lg p-2 min-h-[42px] flex flex-wrap gap-1.5 cursor-text bg-white"
         @click="$refs.input.focus()">
        <template x-for="(email, i) in selected" :key="email">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                <span x-text="email"></span>
                <input type="hidden" :name="fieldName + '[]'" :value="email">
                <button type="button" @click.stop="remove(i)" class="hover:text-red-600 leading-none">&times;</button>
            </span>
        </template>
        <input x-ref="input"
               type="text"
               x-model="search"
               @input="open = true; filterMatches()"
               @focus="open = true; filterMatches()"
               @keydown.enter.prevent="commit()"
               @keydown.comma.prevent="commit()"
               @keydown.semicolon.prevent="commit()"
               @keydown.backspace="if (search === '' && selected.length) selected.pop();"
               class="flex-1 min-w-[200px] outline-none text-sm px-1 py-0.5"
               placeholder="Type or pick an email…">
    </div>
    <div x-show="open && matches.length > 0" @click.outside="open = false" class="relative z-20">
        <ul class="absolute top-1 left-0 right-0 bg-white border border-slate-200 rounded-lg shadow-lg max-h-56 overflow-y-auto text-sm">
            <template x-for="c in matches.slice(0, 20)" :key="c.email">
                <li @click="addEmail(c.email)" class="px-3 py-2 hover:bg-indigo-50 cursor-pointer flex items-center justify-between gap-3">
                    <div>
                        <div class="font-medium text-slate-800" x-text="c.label"></div>
                        <div class="text-xs text-slate-500" x-text="c.email"></div>
                    </div>
                    <span class="text-[10px] text-slate-400 uppercase">contact</span>
                </li>
            </template>
        </ul>
    </div>
</div>
