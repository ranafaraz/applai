<script>
function emailChipPicker(contacts, initialSelected, fieldName) {
    return {
        contacts,
        selected: initialSelected || [],
        matches: [],
        search: '',
        open: false,
        fieldName,
        init() { this.filterMatches(); },
        filterMatches() {
            const q = this.search.toLowerCase().trim();
            this.matches = this.contacts.filter(c =>
                !this.selected.includes(c.email) &&
                (q === '' ||
                    (c.email && c.email.toLowerCase().includes(q)) ||
                    (c.label && c.label.toLowerCase().includes(q)))
            );
        },
        isValidEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },
        addEmail(email) {
            email = (email || '').trim().toLowerCase();
            if (!email || !this.isValidEmail(email)) return;
            if (!this.selected.includes(email)) this.selected.push(email);
            this.search = '';
            this.filterMatches();
            this.$refs.input.focus();
        },
        commit() {
            const v = this.search.trim();
            if (!v) return;
            // If there's an exact contact match, pick it; otherwise treat as raw email
            const hit = this.contacts.find(c => c.email.toLowerCase() === v.toLowerCase());
            this.addEmail(hit ? hit.email : v);
        },
        remove(i) {
            this.selected.splice(i, 1);
            this.filterMatches();
        },
    };
}
</script>
