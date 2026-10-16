import assert from 'node:assert/strict';
import { test } from 'node:test';
import { preserveAdminThemeAcrossNavigation, registerShellComponents } from '../../resources/js/admin/shell.js';

test('a Livewire page swap keeps the selected dark mode before the next Admin shell initializes', () => {
    const root = {
        theme: 'dark',
        getAttribute(key) { return key === 'data-theme' ? this.theme : null; },
        setAttribute(key, value) { if (key === 'data-theme') this.theme = value; },
    };
    const listeners = new Map();
    const page = {
        documentElement: root,
        addEventListener(name, listener) { listeners.set(name, listener); },
    };
    preserveAdminThemeAcrossNavigation(page);
    let afterSwap;
    listeners.get('livewire:navigating')({ detail: { onSwap(callback) { afterSwap = callback; } } });
    root.theme = 'light'; // The incoming HTML carries data-theme="light".
    afterSwap();
    assert.equal(root.theme, 'dark');
});

test('disclosure Escape closes an open menu and restores focus to its trigger', () => {
    const factories = new Map();
    registerShellComponents({ data(name, factory) { factories.set(name, factory); } });
    let focused = false;
    const menu = factories.get('agDisclosure')();
    menu.$root = { querySelector() { return { focus() { focused = true; } }; } };
    menu.$nextTick = (callback) => callback();
    menu.open = true;
    menu.close(true);
    assert.equal(menu.open, false);
    assert.equal(focused, true);

    focused = false;
    menu.open = true;
    menu.close();
    assert.equal(focused, false, 'outside click must not steal focus');
});
