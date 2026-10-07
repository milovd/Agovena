/** Homepage sections (theme::sections.*). */
export function registerHomeComponents(Alpine) {
    Alpine.data('storefrontHero', () => ({
        init() {
            requestAnimationFrame(() => {
                this.$root.classList.add('is-ready');
            });
        },
    }));
}
