/** Shared storefront chrome: disclosures, colour scheme and brand logo (theme::partials.*). */
export function registerChromeComponents(Alpine) {
    Alpine.data('storefrontDisclosure', () => ({
        open: false,
        init() {
            this.open = this.$root.dataset.open === 'true';
        },
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
        closeAndFocus() {
            this.open = false;
            this.$refs.trigger?.focus();
        },
        toggleAndFocusMenu() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.$refs.menu?.querySelector('[role="menuitem"]')?.focus());
            }
        },
    }));

    Alpine.data('storefrontTheme', () => ({
        theme: 'light',
        init() {
            const fallback = this.$root.dataset.defaultTheme || 'system';
            const saved = localStorage.getItem('agovena.theme') || fallback;
            this.apply(saved === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : saved);
        },
        apply(next) {
            this.theme = next === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', this.theme);
            localStorage.setItem('agovena.theme', this.theme);
        },
        toggle() {
            this.apply(this.theme === 'dark' ? 'light' : 'dark');
        },
    }));

    Alpine.data('storefrontBrand', () => ({
        logoReady: false,
        init() {
            const logo = this.$refs.logo;
            this.logoReady = Boolean(logo?.complete && logo.naturalWidth > 0);
        },
        markLogoReady() {
            this.logoReady = true;
        },
    }));
}
