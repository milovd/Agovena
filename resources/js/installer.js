// Installer interactions stay in the external bundle for CSP compatibility.

const copyInstallerCommand = async (button) => {
    const command = button.closest('[data-install-command]')?.querySelector('code')?.textContent?.trim();

    if (!command) {
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(command);
        } else {
            const fallback = document.createElement('textarea');
            fallback.value = command;
            fallback.setAttribute('readonly', '');
            fallback.style.position = 'fixed';
            fallback.style.opacity = '0';
            document.body.appendChild(fallback);
            fallback.select();
            document.execCommand('copy');
            fallback.remove();
        }

        button.dataset.copied = 'true';
        button.setAttribute('aria-label', button.dataset.copiedLabel || button.getAttribute('aria-label') || 'Copied');
        button.setAttribute('title', button.dataset.copiedLabel || button.getAttribute('title') || 'Copied');
        window.setTimeout(() => {
            button.dataset.copied = 'false';
            button.setAttribute('aria-label', button.dataset.copyLabel || button.getAttribute('aria-label') || 'Copy');
            button.setAttribute('title', button.dataset.copyLabel || button.getAttribute('title') || 'Copy');
        }, 2000);
    } catch {
        button.dataset.copied = 'false';
        button.setAttribute('aria-label', button.dataset.copyLabel || button.getAttribute('aria-label') || 'Copy');
        button.setAttribute('title', button.dataset.copyLabel || button.getAttribute('title') || 'Copy');
    }
};

const applyInstallerTheme = (theme) => {
    const nextTheme = theme === 'dark' ? 'dark' : 'light';
    const root = document.documentElement;
    root.setAttribute('data-theme', nextTheme);

    try {
        localStorage.setItem('agovena.theme', nextTheme);
    } catch {
        // Storage can be unavailable in privacy-restricted browsers.
    }

    const toggle = document.querySelector('[data-installer-theme-toggle]');
    if (!toggle) {
        return;
    }

    toggle.querySelector('[data-installer-theme-icon="light"]')?.toggleAttribute('hidden', nextTheme === 'dark');
    toggle.querySelector('[data-installer-theme-icon="dark"]')?.toggleAttribute('hidden', nextTheme !== 'dark');
    const label = nextTheme === 'dark' ? toggle.dataset.labelLight : toggle.dataset.labelDark;
    if (label) {
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
    }
};

const initInstallerTheme = () => {
    let stored = null;
    try {
        stored = localStorage.getItem('agovena.theme');
    } catch {
        stored = null;
    }

    const preferred = stored === 'dark' || stored === 'light'
        ? stored
        : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyInstallerTheme(preferred);

    document.querySelector('[data-installer-theme-toggle]')?.addEventListener('click', () => {
        applyInstallerTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initInstallerTheme, { once: true });
} else {
    initInstallerTheme();
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-installer-copy]');

    if (button) {
        void copyInstallerCommand(button);
    }
});