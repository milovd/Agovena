/** Admin shell: navigation groups, colour scheme, account menu and disclosures (theme::layouts.admin). */
export function registerShellComponents(Alpine) {
    Alpine.data('agAdminNavGroup', () => ({
        open: false,
        init() {
            this.open = this.$root.dataset.open === 'true';
            const key = this.$root.dataset.navKey;
            const active = this.$root.dataset.active === 'true';
            const stored = key ? localStorage.getItem(key) : null;
            if (active) this.open = true;
            else if (stored === '1') this.open = true;
            else if (stored === '0') this.open = false;
            if (key) this.$watch('open', (value) => localStorage.setItem(key, value ? '1' : '0'));
        },
        toggle() {
            this.open = !this.open;
        },
    }));

    Alpine.data('agAdminShell', () => ({
        navOpen: false,
        theme: document.documentElement.getAttribute('data-theme') || 'light',
        applyTheme(next) {
            this.theme = next === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', this.theme);
            localStorage.setItem('agovena.theme', this.theme);
            window.dispatchEvent(new CustomEvent('agovena-theme-changed', { detail: { theme: this.theme } }));
        },
        toggleTheme() {
            this.applyTheme(this.theme === 'dark' ? 'light' : 'dark');
        },
        toggleNav() {
            this.navOpen = !this.navOpen;
        },
        closeNav() {
            this.navOpen = false;
        },
    }));

    Alpine.data('agAdminAccount', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    }));

    Alpine.data('agDisclosure', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    }));
}
