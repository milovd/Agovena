/** Admin product editor tabs (livewire.admin.products.form). */
export function registerProductComponents(Alpine) {
    Alpine.data('agProductTabs', () => ({
        activeTab: 'details',
        selectTab(tab) {
            this.activeTab = tab;
        },
    }));
}
