/** Checkout page (theme::checkout.*). */
export function registerCheckoutComponents(Alpine) {
    Alpine.data('storefrontCheckoutSummary', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        },
    }));
}
