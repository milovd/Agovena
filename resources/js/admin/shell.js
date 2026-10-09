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
        isMobile: window.matchMedia('(max-width: 900px)').matches,
        theme: document.documentElement.getAttribute('data-theme') || 'light',
        init() {
            this.mobileQuery = window.matchMedia('(max-width: 900px)');
            this.onViewportChange = (event) => {
                this.isMobile = event.matches;
                if (!this.isMobile) this.closeNav();
            };
            this.mobileQuery.addEventListener('change', this.onViewportChange);
        },
        destroy() {
            this.mobileQuery?.removeEventListener('change', this.onViewportChange);
        },
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
            if (this.navOpen) {
                this.closeNav();
                return;
            }
            this.navTrigger = document.activeElement;
            this.navOpen = true;
            this.$nextTick(() => this.$refs.sidebar.querySelector('.js-admin-drawer-close')?.focus());
        },
        trapNavFocus(event) {
            if (!this.navOpen || !this.isMobile) return;
            const controls = [...this.$refs.sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                .filter((element) => element.getClientRects().length > 0);
            if (controls.length === 0) return;
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
        closeNav() {
            if (!this.navOpen) return;
            this.navOpen = false;
            const trigger = this.navTrigger;
            this.$nextTick(() => trigger?.focus());
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
