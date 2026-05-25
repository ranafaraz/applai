<script>
// Multi-select picker with type-ahead search for any list of {id, label, sublabel?} records.
// Used to link contacts on an opportunity form and vice versa.
function recordPicker(allRecords, selectedIds, fieldName) {
    return {
        all: allRecords,
        selected: allRecords.filter(r => selectedIds.includes(r.id)),
        filtered: [],
        search: '',
        open: false,
        fieldName,
        init() { this.filterRecords(); },
        filterRecords() {
            const q = this.search.toLowerCase().trim();
            this.filtered = this.all.filter(r =>
                !this.selected.find(s => s.id === r.id) &&
                (q === '' ||
                    (r.label && r.label.toLowerCase().includes(q)) ||
                    (r.sublabel && r.sublabel.toLowerCase().includes(q)))
            );
        },
        add(r) {
            if (!this.selected.find(s => s.id === r.id)) this.selected.push(r);
            this.search = '';
            this.filterRecords();
            this.$refs.pickerInput.focus();
        },
        remove(r) { this.selected = this.selected.filter(s => s.id !== r.id); this.filterRecords(); },
        backspaceTag() { if (this.search === '' && this.selected.length) this.selected.pop(); },
    };
}
</script>
