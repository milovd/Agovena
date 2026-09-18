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

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-installer-copy]');

    if (button) {
        void copyInstallerCommand(button);
    }
});
