document.addEventListener('alpine:init', () => {
    window.Alpine.data('storefrontHero', () => ({
        init() {
            requestAnimationFrame(() => {
                this.$root.classList.add('is-ready');
            });
        },
    }));

    window.Alpine.data('storefrontFileUpload', () => ({
        fileLabel: '',
        onChange(event) {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
            const files = input.files;
            if (!files || files.length === 0) {
                this.fileLabel = '';
                return;
            }
            this.fileLabel = files.length === 1
                ? files[0].name
                : this.$root.dataset.filesSelectedLabel.replace(':count', String(files.length));
        },
    }));

    window.Alpine.data('storefrontCheckoutSummary', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        },
    }));

    window.Alpine.data('storefrontReferral', () => ({
        copied: false,
        async copy() {
            await navigator.clipboard.writeText(this.$refs.link.value);
            this.copied = true;
            window.setTimeout(() => { this.copied = false; }, 2000);
        },
    }));

    window.Alpine.data('storefrontDisclosure', () => ({
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

    window.Alpine.data('storefrontTheme', () => ({
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

    window.Alpine.data('storefrontHeader', () => ({
        navOpen: false,
        drawerTop: 0,
        drawerObserver: null,
        drawerFrame: null,
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

            const refresh = () => this.updateDrawerTop();
            refresh();
            requestAnimationFrame(refresh);
            window.setTimeout(refresh, 200);
            if ('ResizeObserver' in window) {
                this.drawerObserver = new ResizeObserver(refresh);
                document.querySelectorAll('.store-usp, .store-header, .store-discover').forEach((element) => {
                    this.drawerObserver.observe(element);
                });
            }
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
    window.Alpine.data('storefrontProductGallery', () => ({
        images: [],
        index: 0,
        thumbsOverflow: false,
        canScrollLeft: false,
        canScrollRight: false,
        select(index) {
            this.index = index;
            this.$nextTick(() => {
                const thumb = this.$refs.track?.querySelector(`[data-index='${this.index}']`);
                thumb?.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
                this.updateScrollState();
            });
        },
        currentImage() {
            return this.images[this.index] || this.images[0] || '';
        },
        thumbClass(index) {
            return { 'is-active': this.index === index };
        },
        thumbAriaCurrent(index) {
            return this.index === index ? 'true' : 'false';
        },
        arrowClass(canScroll) {
            return { 'is-disabled': !canScroll };
        },
        arrowDisabled(canScroll) {
            return !canScroll;
        },
        arrowAriaHidden(canScroll) {
            return (!canScroll).toString();
        },
        scrollThumbs(direction) {
            const track = this.$refs.track;
            if (!track) return;
            const styles = getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap) || 12;
            const size = parseFloat(styles.getPropertyValue('--thumb-size')) || 72;
            const step = Math.max(size + gap, track.clientWidth * 0.85);
            track.scrollBy({ left: direction * step, behavior: 'smooth' });
        },
        layoutThumbs() {
            const track = this.$refs.track;
            if (!track) return;
            const styles = getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap) || 12;
            const pad = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0);
            const inner = Math.max(0, track.clientWidth - pad);
            const count = track.children.length;
            const slots = Math.max(1, Math.floor((inner + gap) / (64 + gap)));
            if (count > slots && inner > 0) {
                const size = (inner - ((slots - 1) * gap)) / slots;
                track.style.setProperty('--thumb-size', `${size}px`);
                track.classList.add('is-fill');
            } else {
                track.style.removeProperty('--thumb-size');
                track.classList.remove('is-fill');
            }
            this.updateScrollState();
        },
        updateScrollState() {
            const track = this.$refs.track;
            if (!track) {
                this.thumbsOverflow = false;
                this.canScrollLeft = false;
                this.canScrollRight = false;
                return;
            }
            const max = track.scrollWidth - track.clientWidth;
            this.thumbsOverflow = max > 4;
            this.canScrollLeft = this.thumbsOverflow && track.scrollLeft > 4;
            this.canScrollRight = this.thumbsOverflow && track.scrollLeft < max - 4;
        },
        init() {
            this.images = JSON.parse(this.$root.dataset.images || '[]');
            this.$nextTick(() => {
                this.layoutThumbs();
                const track = this.$refs.track;
                if (!track) return;
                track.addEventListener('scroll', () => this.updateScrollState(), { passive: true });
                window.addEventListener('resize', () => this.layoutThumbs());
                if (typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(() => this.layoutThumbs()).observe(track);
                }
            });
        },
    }));

    window.Alpine.data('storefrontProductPanels', () => ({
        tab: 'details',
        reviewsOn: false,
        init() {
            this.reviewsOn = this.$root.dataset.reviewsOn === 'true';
            this.tab = window.location.hash === '#reviews' && this.reviewsOn
                ? 'reviews'
                : this.$root.dataset.defaultTab;
        },
        openReviews() {
            if (!this.reviewsOn) return;
            this.tab = 'reviews';
            history.replaceState(null, '', '#reviews');
            this.$nextTick(() => this.$refs.reviews?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
        selectTab(tab) {
            this.tab = tab;
            history.replaceState(null, '', `#${tab}`);
        },
    }));

    window.Alpine.data('storefrontPushInstaller', () => ({
        configured: false,
        configUrl: '',
        subscribeUrl: '',
        unsubscribeUrl: '',
        messages: {},
        supported: false,
        subscribed: false,
        busy: false,
        status: '',
        registration: null,
        init() {
            this.configured = this.$root.dataset.configured === 'true';
            this.configUrl = this.$root.dataset.configUrl || '';
            this.subscribeUrl = this.$root.dataset.subscribeUrl || '';
            this.unsubscribeUrl = this.$root.dataset.unsubscribeUrl || '';
            this.messages = JSON.parse(this.$root.dataset.messages || '{}');
            this.supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
            if (!this.supported) {
                this.status = this.messages.unsupported;
                return;
            }
            if (!this.configured) this.status = this.messages.notConfigured;
            this.register();
        },
        async register() {
            try {
                this.registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                const subscription = await this.registration.pushManager.getSubscription();
                this.subscribed = Boolean(subscription);
            } catch {
                this.status = this.messages.failed;
            }
        },
        async install() {
            if (!this.supported || !this.configured) return;
            this.busy = true;
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.status = this.messages.permissionDenied;
                    return;
                }
                const configResponse = await fetch(this.configUrl, { headers: { Accept: 'application/json' } });
                const config = await configResponse.json();
                if (!config.configured || !config.publicKey) {
                    this.configured = false;
                    this.status = this.messages.notConfigured;
                    return;
                }
                const subscription = await this.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.decodeKey(config.publicKey),
                });
                const response = await fetch(this.subscribeUrl, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(subscription.toJSON()),
                });
                if (!response.ok) throw new Error('subscription_failed');
                this.subscribed = true;
                this.status = this.messages.installed;
            } catch {
                this.status = this.messages.failed;
            } finally {
                this.busy = false;
            }
        },
        async remove() {
            this.busy = true;
            try {
                const subscription = await this.registration?.pushManager.getSubscription();
                if (subscription) {
                    await fetch(this.unsubscribeUrl, {
                        method: 'DELETE',
                        headers: this.headers(),
                        body: JSON.stringify({ endpoint: subscription.endpoint }),
                    });
                    await subscription.unsubscribe();
                }
                this.subscribed = false;
                this.status = this.messages.removed;
            } catch {
                this.status = this.messages.failed;
            } finally {
                this.busy = false;
            }
        },
        headers() {
            return {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
        },
        decodeKey(value) {
            const padding = '='.repeat((4 - (value.length % 4)) % 4);
            const normalized = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = window.atob(normalized);
            return Uint8Array.from(raw, (character) => character.charCodeAt(0));
        },
    }));
    window.Alpine.data('storefrontAccountNav', () => ({
        open: false,
        init() {
            this.open = this.$root.dataset.open === 'true';
        },
        toggle() {
            this.open = !this.open;
        },
    }));
});

const banner = document.querySelector('[data-cookie-banner]');
const bannerDialog = document.querySelector('[data-cookie-banner-dialog]');
const panel = document.querySelector('[data-cookie-panel]');
const dialog = document.querySelector('[data-cookie-dialog]');
const analyticsToggle = document.querySelector('[data-cookie-analytics]');
const essentialToggle = document.querySelector('[data-cookie-essential]');
const endpoint = banner instanceof HTMLElement ? banner.dataset.cookieEndpoint : '';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let hasConsent = banner instanceof HTMLElement && banner.dataset.cookieHasConsent === 'true';
let lastFocus = null;

function setScrollLock(locked) {
    document.documentElement.classList.toggle('is-cookie-locked', locked);
}

function setError(visible) {
    document.querySelectorAll('[data-cookie-error]').forEach((error) => {
        if (error instanceof HTMLElement) {
            error.hidden = !visible;
        }
    });
}

function setBusy(busy) {
    document.querySelectorAll('[data-cookie-banner] button, [data-cookie-panel] button').forEach((button) => {
        if (button instanceof HTMLButtonElement) {
            button.disabled = busy;
        }
    });

    [banner, panel].forEach((element) => {
        if (element instanceof HTMLElement) {
            element.setAttribute('aria-busy', busy ? 'true' : 'false');
        }
    });
}

function showBanner(visible) {
    if (!(banner instanceof HTMLElement)) {
        return;
    }

    banner.hidden = !visible;
    if (visible) {
        setScrollLock(true);
        window.requestAnimationFrame(() => {
            if (bannerDialog instanceof HTMLElement) {
                bannerDialog.focus();
            }
        });
    } else if (!(panel instanceof HTMLElement) || panel.hidden) {
        setScrollLock(false);
    }
}

function focusableElements(root) {
    if (!(root instanceof HTMLElement)) {
        return [];
    }

    return Array.from(root.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )).filter((element) => {
        if (!(element instanceof HTMLElement) || element.hasAttribute('disabled')) {
            return false;
        }

        return Boolean(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    });
}

function trapFocus(root, event) {
    if (event.key !== 'Tab') {
        return;
    }

    const focusable = focusableElements(root);
    if (focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function syncSettings() {
    if (essentialToggle instanceof HTMLInputElement) {
        essentialToggle.checked = true;
    }
}

function setTab(name) {
    document.querySelectorAll('[data-cookie-tab]').forEach((tab) => {
        const active = tab.getAttribute('data-cookie-tab') === name;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    document.querySelectorAll('[data-cookie-pane]').forEach((pane) => {
        const active = pane.getAttribute('data-cookie-pane') === name;
        pane.classList.toggle('is-active', active);
        if (pane instanceof HTMLElement) {
            pane.hidden = !active;
        }
    });
}

function showPanel(visible) {
    if (!(panel instanceof HTMLElement)) {
        return;
    }

    if (visible) {
        lastFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        setError(false);
        syncSettings();
        setTab('consent');
        showBanner(false);
        panel.hidden = false;
        setScrollLock(true);
        window.requestAnimationFrame(() => {
            if (dialog instanceof HTMLElement) {
                dialog.focus();
            }
        });
        return;
    }

    panel.hidden = true;
    if (!hasConsent) {
        showBanner(true);
    } else {
        setScrollLock(false);
    }

    if (hasConsent && lastFocus instanceof HTMLElement) {
        lastFocus.focus();
    }
    lastFocus = null;
}

function updateConsentMeta(consent) {
    document.querySelectorAll('[data-consent-meta]').forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        const id = String(consent?.id || '');
        const date = String(consent?.date || '');
        if (!id || !date) {
            element.textContent = element.dataset.consentEmpty || '';
            return;
        }

        const template = element.dataset.consentTemplate || '';
        element.textContent = template.replace(':id', id).replace(':date', date);
    });
}

async function submitChoice(choice, closePanel = false) {
    if (!endpoint || !csrfToken) {
        setError(true);
        return false;
    }

    setError(false);
    setBusy(true);

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({ choice }),
        });

        if (!response.ok) {
            throw new Error('Consent request failed');
        }

        const data = await response.json();
        if (data?.consent?.choice !== choice) {
            throw new Error('Consent response was invalid');
        }

        hasConsent = true;
        if (banner instanceof HTMLElement) {
            banner.dataset.cookieHasConsent = 'true';
        }
        updateConsentMeta(data.consent);
        if (closePanel) {
            showPanel(false);
        } else {
            showBanner(false);
        }
        setScrollLock(false);
        return true;
    } catch {
        setError(true);
        return false;
    } finally {
        setBusy(false);
    }
}

essentialToggle?.addEventListener('click', (event) => {
    event.preventDefault();
    if (essentialToggle instanceof HTMLInputElement) {
        essentialToggle.checked = true;
    }

    const message = document.querySelector('[data-essential-msg]');
    if (message instanceof HTMLElement) {
        message.hidden = false;
    }
});

essentialToggle?.addEventListener('change', () => {
    if (essentialToggle instanceof HTMLInputElement) {
        essentialToggle.checked = true;
    }
});

document.querySelectorAll('[data-cookie-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
        const name = tab.getAttribute('data-cookie-tab');
        if (name) {
            setTab(name);
        }
    });
});

document.querySelectorAll('[data-cookie-choice-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const choice = form instanceof HTMLFormElement ? form.dataset.cookieChoice : '';
        if (choice) {
            void submitChoice(choice);
        }
    });
});

document.querySelector('[data-cookie-settings]')?.addEventListener('click', () => showPanel(true));
document.querySelectorAll('[data-cookie-open]').forEach((trigger) => {
    trigger.addEventListener('click', () => showPanel(true));
});
document.querySelector('[data-cookie-reject-panel]')?.addEventListener('click', () => {
    void submitChoice('necessary', true);
});
document.querySelector('[data-cookie-save]')?.addEventListener('click', () => {
    const choice = analyticsToggle instanceof HTMLInputElement && analyticsToggle.checked ? 'analytics' : 'necessary';
    void submitChoice(choice, true);
});
document.querySelector('[data-cookie-close]')?.addEventListener('click', () => showPanel(false));
panel?.addEventListener('click', (event) => {
    if (event.target === panel) {
        showPanel(false);
    }
});

document.addEventListener('keydown', (event) => {
    if (panel instanceof HTMLElement && !panel.hidden) {
        if (event.key === 'Escape') {
            event.preventDefault();
            showPanel(false);
            return;
        }
        trapFocus(dialog, event);
        return;
    }

    if (banner instanceof HTMLElement && !banner.hidden) {
        trapFocus(bannerDialog, event);
    }
});

if (banner instanceof HTMLElement && !banner.hidden) {
    setScrollLock(true);
}
