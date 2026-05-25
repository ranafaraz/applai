{{--
    Inline "+ New Contact" modal usable from any form. Posts JSON to
    contacts.quick-store and dispatches a window event 'contact-created'
    that the parent recordPicker listens for to auto-select the new contact.

    Renders inside the parent x-data scope; expects a function `add(record)`
    to be available on the parent (matches recordPicker exposed API).
--}}
<div x-data="quickContactModal()" x-cloak>
    <button type="button"
            @click="open = true"
            class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2.5 py-1 rounded-md mt-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Contact
    </button>

    <div x-show="open" @keydown.escape.window="open = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div @click.outside="open = false" class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-800">Add New Contact</h3>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <div x-show="error" class="bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2 rounded">
                <span x-text="error"></span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">First Name *</label>
                    <input type="text" x-model="form.first_name" class="w-full px-3 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Last Name</label>
                    <input type="text" x-model="form.last_name" class="w-full px-3 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Email *</label>
                    <input type="email" x-model="form.email" class="w-full px-3 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Company</label>
                    <input type="text" x-model="form.company" class="w-full px-3 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Job Title</label>
                    <input type="text" x-model="form.job_title" class="w-full px-3 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Phone</label>
                    <input type="text" x-model="form.phone" class="w-full px-3 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open = false" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-md">Cancel</button>
                <button type="button" @click="save" :disabled="saving" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-md disabled:opacity-60">
                    <span x-text="saving ? 'Saving…' : 'Save & Link'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function quickContactModal() {
    return {
        open: false,
        saving: false,
        error: null,
        form: { first_name: '', last_name: '', email: '', company: '', job_title: '', phone: '' },
        async save() {
            this.error = null;
            if (!this.form.first_name || !this.form.email) {
                this.error = 'First name and email are required.';
                return;
            }
            this.saving = true;
            try {
                const res = await fetch('{{ route('contacts.quick-store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                if (!res.ok) {
                    const j = await res.json().catch(() => ({}));
                    this.error = j.message || j.error || ('Save failed (HTTP ' + res.status + ')');
                    this.saving = false;
                    return;
                }
                const data = await res.json();
                // Tell the parent recordPicker to auto-select the new contact
                window.dispatchEvent(new CustomEvent('contact-created', { detail: data }));
                this.open = false;
                this.saving = false;
                this.form = { first_name: '', last_name: '', email: '', company: '', job_title: '', phone: '' };
            } catch (e) {
                this.error = 'Network error: ' + e.message;
                this.saving = false;
            }
        },
    };
}
</script>
