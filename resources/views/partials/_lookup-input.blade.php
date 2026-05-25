{{--
    A text input backed by a master lookup table. Renders as:
      <input list="<id>_list" ...>
      <datalist id="<id>_list">  pre-seeded options + tenant-recorded ones  </datalist>

    Usage:
      @include('partials._lookup-input', [
          'type' => 'country',        // lookup type
          'name' => 'country',        // form field name
          'value' => old('country'),  // initial value
          'placeholder' => '...',     // optional
          'id' => 'contact_country',  // optional dom id (defaults to name)
      ])
--}}
@php
    $lookupId = ($id ?? $name) . '_lookup';
    $items    = \App\Models\Lookup::listFor($type, auth()->user()?->tenant_id);
@endphp
<input type="text"
       name="{{ $name }}"
       value="{{ $value ?? '' }}"
       list="{{ $lookupId }}"
       autocomplete="off"
       placeholder="{{ $placeholder ?? '' }}"
       class="{{ $class ?? 'w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500' }}">
<datalist id="{{ $lookupId }}">
    @foreach($items as $item)
        <option value="{{ $item->value }}">
    @endforeach
</datalist>
