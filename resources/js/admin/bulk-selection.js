/*
 * Bulk selection on Admin lists. Row checkboxes bind with deferred `wire:model`, so ticking
 * rows and "select this page" happen in the browser without a request; the selection travels
 * to the server with the bulk action itself. The server still validates every id against the
 * filtered current page before it changes anything.
 *
 * Root element data attributes:
 *   data-bulk-model         Livewire property that holds the selected ids
 *   data-bulk-visible       JSON list of ids on the current page
 *   data-bulk-count-label   translated "__COUNT__ selected" text
 *   data-bulk-select-label / data-bulk-deselect-label   labels of the page toggle
 */
export function registerBulkSelection(Alpine) {
    Alpine.data('agBulkSelection', () => ({
        // Nested components (the actions menu) evaluate these getters too, where $root is their
        // own element; keep a reference to the selection root instead.
        bulkRoot: null,

        init() {
            this.bulkRoot = this.$el;
        },

        get bulkData() {
            return this.bulkRoot ? this.bulkRoot.dataset : {};
        },

        get modelName() {
            return this.bulkData.bulkModel || '';
        },

        get selection() {
            const value = this.modelName ? this.$wire.get(this.modelName) : [];

            return Array.isArray(value) ? value.map(String) : [];
        },

        get visibleIds() {
            try {
                const ids = JSON.parse(this.bulkData.bulkVisible || '[]');

                return Array.isArray(ids) ? ids.map(String) : [];
            } catch {
                return [];
            }
        },

        get count() {
            return this.selection.length;
        },

        get isEmpty() {
            return this.count === 0;
        },

        get allSelected() {
            const selection = this.selection;
            const visible = this.visibleIds;

            return visible.length > 0 && visible.every((id) => selection.includes(id));
        },

        get ariaChecked() {
            return this.allSelected ? 'true' : 'false';
        },

        get countLabel() {
            return (this.bulkData.bulkCountLabel || '').replace('__COUNT__', String(this.count));
        },

        get toggleLabel() {
            return this.allSelected ? this.bulkData.bulkDeselectLabel : this.bulkData.bulkSelectLabel;
        },

        toggleAll() {
            if (this.modelName) {
                this.$wire.set(this.modelName, this.allSelected ? [] : this.visibleIds, false);
            }
        },
    }));
}
