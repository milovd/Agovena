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

        button.textContent = button.dataset.copiedLabel || button.textContent;
        window.setTimeout(() => {
            button.textContent = button.dataset.copyLabel || button.textContent;
        }, 2000);
    } catch {
        button.textContent = button.dataset.copyLabel || button.textContent;
    }
};

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-installer-copy]');

    if (button) {
        void copyInstallerCommand(button);
    }
});
