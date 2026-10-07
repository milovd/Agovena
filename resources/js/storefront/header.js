/** Storefront header: responsive nav, mobile drawer, categories and search suggestions (theme::partials.header). */
export function registerHeaderComponents(Alpine) {
    Alpine.data('storefrontHeader', () => ({
        navOpen: false,
        drawerTop: 0,
        drawerObserver: null,
        drawerFrame: null,
        navFrame: null,
        catsOpen: false,
        mobileCatsOpen: false,
        mobileCategoryOpen: null,
        mobileAccountOpen: false,
        activeCat: null,
        suggestOpen: false,
        suggestLoading: false,
        suggestItems: [],
        suggestQuery: '',
        suggestUrl: '',
        searchBaseUrl: '',
        suggestTimer: null,
        labels: { searching: '', noMatches: '', viewAll: '' },
        init() {
            const root = this.$root;
            this.suggestQuery = root.dataset.suggestQuery || '';
            this.suggestUrl = root.dataset.suggestUrl || '';
            this.searchBaseUrl = root.dataset.searchBaseUrl || '';
            this.labels = {
                searching: root.dataset.searchingLabel || '',
                noMatches: root.dataset.noMatchesLabel || '',
                viewAll: root.dataset.viewAllLabel || '',
            };

            const refresh = () => {
                this.updateNavLayout();
                this.updateDrawerTop();
            };
            refresh();
            requestAnimationFrame(refresh);
            window.setTimeout(refresh, 200);
            document.fonts?.ready.then(() => this.scheduleNavLayoutRefresh());
            if ('ResizeObserver' in window) {
                this.drawerObserver = new ResizeObserver(refresh);
                document.querySelectorAll('.store-usp, .store-header, .store-discover').forEach((element) => {
                    this.drawerObserver.observe(element);
                });
            }
        },
        handleResize() {
            this.scheduleNavLayoutRefresh();
            this.scheduleDrawerTopRefresh();
        },
        updateNavLayout() {
            const root = this.$root;
            root.classList.remove('is-nav-compact');
            if (window.innerWidth < 1100) return;

            const inner = root.querySelector('.store-header__inner');
            const nav = this.$refs.desktopNav;
            const search = inner?.querySelector('.store-header__search-wrap:not(.store-header__search-wrap--mobile)');
            const actions = inner?.querySelector('.store-header__actions');
            if (!inner || !nav || !actions) return;

            const lastLink = [...nav.querySelectorAll('.store-nav__link')].at(-1);
            const linkRight = lastLink?.getBoundingClientRect().right ?? nav.getBoundingClientRect().right;
            const searchRect = search?.getBoundingClientRect();
            const actionsRect = actions.getBoundingClientRect();
            const innerRight = inner.getBoundingClientRect().right - parseFloat(getComputedStyle(inner).paddingRight);
            const compact = linkRight > (searchRect?.left ?? actionsRect.left) - 8
                || (searchRect && searchRect.right > actionsRect.left - 8)
                || actionsRect.right > innerRight + 1;

            root.classList.toggle('is-nav-compact', compact);
            if (!compact && this.navOpen) this.closeDrawer();
            if (compact) this.closeCategories();
        },
        scheduleNavLayoutRefresh() {
            if (this.navFrame !== null) return;
            this.navFrame = requestAnimationFrame(() => {
                this.navFrame = null;
                this.updateNavLayout();
            });
        },
        updateDrawerTop() {
            const chromeParts = [
                document.querySelector('.store-usp'),
                document.querySelector('.store-header'),
                document.querySelector('.store-discover'),
            ].filter(Boolean);
            const bottom = chromeParts.reduce(
                (currentBottom, element) => Math.max(currentBottom, element.getBoundingClientRect().bottom),
                0,
            );
            this.drawerTop = Math.max(0, Math.round(bottom));
        },
        scheduleDrawerTopRefresh() {
            if (this.drawerFrame !== null) return;
            this.drawerFrame = requestAnimationFrame(() => {
                this.drawerFrame = null;
                this.updateDrawerTop();
            });
        },
        syncDrawerLock() {
            document.body.classList.toggle('store-drawer-open', this.navOpen);
        },
        toggleNav() {
            this.updateDrawerTop();
            if (this.navOpen) {
                this.mobileCatsOpen = false;
                this.mobileCategoryOpen = null;
                this.mobileAccountOpen = false;
            }
            this.navOpen = !this.navOpen;
        },
        closeAll() {
            this.navOpen = false;
            this.catsOpen = false;
            this.mobileCatsOpen = false;
            this.mobileCategoryOpen = null;
            this.mobileAccountOpen = false;
            this.suggestOpen = false;
        },
        openCategories() {
            this.catsOpen = true;
        },
        closeCategories() {
            this.catsOpen = false;
            this.activeCat = null;
        },
        setActiveCategory(categoryId) {
            this.activeCat = categoryId;
        },
        isCategoryActive(categoryId, isFirst) {
            return this.activeCat === categoryId || (this.activeCat === null && isFirst);
        },
        categoryClass(categoryId, isFirst) {
            return { 'is-active': this.isCategoryActive(categoryId, isFirst) };
        },
        closeDrawer() {
            this.navOpen = false;
            this.mobileCatsOpen = false;
            this.mobileCategoryOpen = null;
            this.mobileAccountOpen = false;
            this.closeSuggest();
        },
        toggleMobileCategories() {
            this.mobileCatsOpen = !this.mobileCatsOpen;
            if (!this.mobileCatsOpen) this.mobileCategoryOpen = null;
        },
        toggleMobileCategory(id) {
            this.mobileCategoryOpen = this.mobileCategoryOpen === id ? null : id;
        },
        toggleMobileAccount() {
            this.mobileAccountOpen = !this.mobileAccountOpen;
        },
        closeDrawerOnNavigate() {
            this.navOpen = false;
        },
        async runSuggest() {
            const query = this.suggestQuery.trim();
            if (query.length < 2) {
                this.suggestItems = [];
                this.suggestOpen = false;
                return;
            }
            this.suggestLoading = true;
            try {
                const response = await fetch(`${this.suggestUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json();
                this.suggestItems = data.items || [];
                this.suggestOpen = true;
            } catch {
                this.suggestItems = [];
                this.suggestOpen = false;
            } finally {
                this.suggestLoading = false;
            }
        },
        onSuggestInput() {
            clearTimeout(this.suggestTimer);
            this.suggestTimer = setTimeout(() => this.runSuggest(), 180);
        },
        closeSuggest() {
            this.suggestOpen = false;
        },
        searchResultsUrl() {
            const query = this.suggestQuery.trim();

            return `${this.searchBaseUrl}?q=${encodeURIComponent(query)}`;
        },
        clearSuggest() {
            this.suggestQuery = '';
            this.suggestItems = [];
            this.suggestOpen = false;
            this.suggestLoading = false;
        },
    }));
}
