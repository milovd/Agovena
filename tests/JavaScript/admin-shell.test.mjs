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
