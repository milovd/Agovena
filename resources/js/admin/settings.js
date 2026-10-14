export function registerSettingsComponents(Alpine) {
    Alpine.data('agSettingsTabScroller', () => ({
        observer: null,
        init() {
            this.observer = new MutationObserver(() => this.revealActiveTab());
            this.observer.observe(this.$el, {
                subtree: true,
                attributes: true,
                attributeFilter: ['aria-selected'],
            });
            this.$nextTick(() => this.revealActiveTab());
        },
        revealActiveTab() {
            const tabs = this.$el.querySelector('[role="tablist"]');
            const active = tabs?.querySelector('[aria-selected="true"]');
            if (!active || tabs.scrollWidth <= tabs.clientWidth) return;

            const viewport = tabs.getBoundingClientRect();
            const selected = active.getBoundingClientRect();
            if (selected.left < viewport.left) {
                tabs.scrollLeft -= viewport.left - selected.left + 4;
            } else if (selected.right > viewport.right) {
                tabs.scrollLeft += selected.right - viewport.right + 4;
            }
        },
        destroy() {
            this.observer?.disconnect();
        },
    }));
}
