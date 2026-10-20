/** Keep the chosen colour mode and sidebar state before Alpine initializes the next Livewire-navigated page. */
export function preserveAdminThemeAcrossNavigation(page = document) {
    page.addEventListener('livewire:navigating', (event) => {
        const theme = page.documentElement.getAttribute('data-theme');
        const sidebar = page.documentElement.getAttribute('data-sidebar');
        event.detail.onSwap(() => {
            if (theme === 'dark' || theme === 'light') page.documentElement.setAttribute('data-theme', theme);
            if (sidebar === 'collapsed') page.documentElement.setAttribute('data-sidebar', sidebar);
        });
    });
}

const SIDEBAR_STORAGE_KEY = 'agovena.admin.sidebar';

/** Whether a keyboard shortcut should stay with the focused field (text input, select, rich text editor). */
function isEditingText(target) {
    return target instanceof Element
        && target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])') !== null;
}

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
            // On the icon rail the sub-pages are hidden: expand the sidebar and show this section.
            const onRail = document.documentElement.getAttribute('data-sidebar') === 'collapsed'
                && window.matchMedia('(min-width: 901px)').matches;
            if (onRail) {
                this.open = true;
                window.dispatchEvent(new CustomEvent('agovena-sidebar-expand'));
                return;
            }
            this.open = !this.open;
        },
    }));

    Alpine.data('agAdminShell', () => ({
        navOpen: false,
        collapsed: document.documentElement.getAttribute('data-sidebar') === 'collapsed',
        isMobile: window.matchMedia('(max-width: 900px)').matches,
        theme: document.documentElement.getAttribute('data-theme') || 'light',
        init() {
            this.mobileQuery = window.matchMedia('(max-width: 900px)');
            this.onViewportChange = (event) => {
                this.isMobile = event.matches;
                if (!this.isMobile) this.closeNav();
            };
            this.mobileQuery.addEventListener('change', this.onViewportChange);
            if (!this.isMobile) this.$nextTick(() => this.revealCurrentNavItem());
        },
        /** Long sidebars scroll; keep the current page (or its section) visible on load and when the drawer opens. */
        revealCurrentNavItem() {
            const current = this.$refs.sidebar?.querySelector('[aria-current="page"], .admin-nav__trigger--active');
            current?.scrollIntoView({ block: 'nearest' });
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
        /** Desktop: collapse the sidebar to an icon rail. Mobile: open the navigation drawer instead. */
        toggleSidebar() {
            if (this.isMobile) {
                this.toggleNav();
                return;
            }
            this.collapsed = !this.collapsed;
            document.documentElement.setAttribute('data-sidebar', this.collapsed ? 'collapsed' : 'expanded');
            try {
                localStorage.setItem(SIDEBAR_STORAGE_KEY, this.collapsed ? 'collapsed' : 'expanded');
            } catch (error) {
                // Private browsing can refuse storage; the toggle still works for this page.
            }
            this.hideTooltip();
        },
        expandSidebar() {
            if (this.collapsed && !this.isMobile) this.toggleSidebar();
        },
        onShortcut(event) {
            if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey || event.key.toLowerCase() !== 'b') return;
            if (isEditingText(event.target)) return;
            event.preventDefault();
            this.toggleSidebar();
        },
        /** Labels are visually hidden on the icon rail; show the hovered or focused one beside it. */
        showTooltip(event) {
            const tooltip = this.$refs.navTooltip;
            if (!tooltip || !this.collapsed || this.isMobile) return;
            const target = event.target instanceof Element ? event.target.closest('[data-tooltip]') : null;
            if (!target) return;
            const box = target.getBoundingClientRect();
            tooltip.textContent = target.dataset.tooltip;
            tooltip.style.top = `${box.top + box.height / 2}px`;
            tooltip.style.left = `${box.right + 12}px`;
            tooltip.hidden = false;
        },
        hideTooltip() {
            if (this.$refs.navTooltip) this.$refs.navTooltip.hidden = true;
        },
        toggleNav() {
            if (this.navOpen) {
                this.closeNav();
                return;
            }
            this.navTrigger = document.activeElement;
            this.navOpen = true;
            this.$nextTick(() => {
                this.revealCurrentNavItem();
                this.$refs.sidebar.querySelector('.js-admin-drawer-close')?.focus();
            });
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
        close(restoreFocus = false) {
            if (!this.open) return;
            this.open = false;
            if (restoreFocus) {
                this.$nextTick(() => this.$root.querySelector('button[aria-controls]')?.focus());
            }
        },
    }));
}
