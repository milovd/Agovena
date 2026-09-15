document.addEventListener('alpine:init', () => {
    window.Alpine.data('storefrontDisclosure', () => ({
        open: false,
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
        suggestTimer: null,
        labels: { searching: '', noMatches: '', viewAll: '' },
        init() {
            const root = this.$root;
            this.suggestQuery = root.dataset.suggestQuery || '';
            this.suggestUrl = root.dataset.suggestUrl || '';
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
        clearSuggest() {
            this.suggestQuery = '';
            this.suggestItems = [];
            this.suggestOpen = false;
            this.suggestLoading = false;
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
