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
