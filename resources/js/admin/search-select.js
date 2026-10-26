/*
 * Search select (x-ag.search-select) and search panels on Admin pages. Livewire owns the state;
 * this only focuses the search field when the panel opens and closes an open panel on Escape or
 * a click outside it.
 *
 * Root element data attributes:
 *   data-close-method   Livewire method that closes the panel (for example closePicker)
 *   data-close-model    Livewire property to clear instead (for example invoiceSearch)
 *   data-open-selector  selector that exists inside the root only while the panel is open
 */
export function registerSearchSelect(Alpine) {
    Alpine.data('agSearchSelect', () => ({
        init() {
            const field = this.$el.querySelector('.ag-search-select__panel input[type="search"]');
            if (field) {
                field.focus();
            }
        },

        isOpen() {
            return this.$el.querySelector(this.$el.dataset.openSelector || '.ag-search-select__panel') !== null;
        },

        close() {
            if (!this.isOpen()) return;
            const method = this.$el.dataset.closeMethod;
            const model = this.$el.dataset.closeModel;
            if (method) {
                this.$wire.call(method);
            } else if (model) {
                this.$wire.set(model, '');
            }
        },
    }));
}
