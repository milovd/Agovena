import { test } from 'node:test';
import assert from 'node:assert/strict';
import { registerShellComponents } from '../../resources/js/admin/shell.js';

const components = new Map();
registerShellComponents({ data: (name, factory) => components.set(name, factory) });

function initializeGroup({ defaultOpen = false, active = false, saved = null } = {}) {
    const originalStorage = Object.getOwnPropertyDescriptor(globalThis, 'localStorage');
    Object.defineProperty(globalThis, 'localStorage', {
        configurable: true,
        value: {
            getItem: () => saved,
            setItem: () => {},
        },
    });
    try {
        const group = components.get('agAdminNavGroup')();
        group.$root = {
            dataset: {
                open: defaultOpen ? 'true' : 'false',
                active: active ? 'true' : 'false',
                navKey: 'agovena.admin.nav.v6.test',
            },
        };
        group.$watch = () => {};
        group.init();
        return group.open;
    } finally {
        if (originalStorage) Object.defineProperty(globalThis, 'localStorage', originalStorage);
        else delete globalThis.localStorage;
    }
}

test('a previously expanded group stays expanded on the next page', () => {
    assert.equal(initializeGroup({ saved: '1' }), true);
});

test('the active group opens even if it was previously collapsed', () => {
    assert.equal(initializeGroup({ saved: '0', active: true }), true);
});

test('a previously collapsed group stays collapsed', () => {
    assert.equal(initializeGroup({ defaultOpen: true, saved: '0' }), false);
});

test('opening the mobile drawer focuses its close control and closing restores the trigger', () => {
    const previousDocument = globalThis.document;
    const previousWindow = globalThis.window;
    let triggerFocused = 0;
    let closeFocused = 0;
    const trigger = { focus: () => { triggerFocused++; } };
    const close = { focus: () => { closeFocused++; } };

    globalThis.document = {
        documentElement: { getAttribute: () => 'light' },
        activeElement: trigger,
    };
    globalThis.window = { matchMedia: () => ({ matches: true }) };

    try {
        const shell = components.get('agAdminShell')();
        shell.$refs = { sidebar: { querySelector: () => close } };
        shell.$nextTick = (callback) => callback();
        shell.toggleNav();
        assert.equal(shell.navOpen, true);
        assert.equal(closeFocused, 1);
        shell.closeNav();
        assert.equal(shell.navOpen, false);
        assert.equal(triggerFocused, 1);
    } finally {
        if (previousDocument === undefined) delete globalThis.document;
        else globalThis.document = previousDocument;
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
});

test('Tab and Shift+Tab stay inside the open mobile drawer', () => {
    const previousDocument = globalThis.document;
    const previousWindow = globalThis.window;
    let firstFocused = 0;
    let lastFocused = 0;
    let prevented = 0;
    const first = { focus: () => { firstFocused++; }, getClientRects: () => [1] };
    const last = { focus: () => { lastFocused++; }, getClientRects: () => [1] };
    globalThis.document = { documentElement: { getAttribute: () => 'light' }, activeElement: last };
    globalThis.window = { matchMedia: () => ({ matches: true }) };

    try {
        const shell = components.get('agAdminShell')();
        shell.navOpen = true;
        shell.$refs = { sidebar: { querySelectorAll: () => [first, last] } };
        const event = (shiftKey) => ({ shiftKey, preventDefault: () => { prevented++; } });
        shell.trapNavFocus(event(false));
        assert.equal(firstFocused, 1);
        globalThis.document.activeElement = first;
        shell.trapNavFocus(event(true));
        assert.equal(lastFocused, 1);
        assert.equal(prevented, 2);
    } finally {
        if (previousDocument === undefined) delete globalThis.document;
        else globalThis.document = previousDocument;
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
});
