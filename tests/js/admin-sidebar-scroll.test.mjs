import assert from 'node:assert/strict';
import { test } from 'node:test';
import { registerShellComponents } from '../../resources/js/admin/shell.js';

function reveal(itemRect, portRect = { top: 80, bottom: 400 }) {
    const previousDocument = globalThis.document;
    const previousWindow = globalThis.window;
    globalThis.document = { documentElement: { getAttribute: () => 'light' } };
    globalThis.window = { matchMedia: () => ({ matches: false }) };

    try {
        const factories = new Map();
        registerShellComponents({ data(name, factory) { factories.set(name, factory); } });
        const shell = factories.get('agAdminShell')();
        const scrollport = { scrollTop: 100, getBoundingClientRect: () => portRect };
        const current = itemRect === null ? null : {
            getBoundingClientRect: () => itemRect,
            scrollIntoView() {
                assert.fail('scrollIntoView changes the browser Tab starting point and can scroll the page');
            },
        };
        shell.$refs = {
            sidebar: {
                querySelector(selector) {
                    return selector === '.admin-sidebar__scroll' ? scrollport : current;
                },
            },
        };
        shell.revealCurrentNavItem();

        return scrollport.scrollTop;
    } finally {
        if (previousDocument === undefined) delete globalThis.document;
        else globalThis.document = previousDocument;
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
}

test('revealing an item below the sidebar viewport scrolls only the sidebar', () => {
    assert.equal(reveal({ top: 450, bottom: 490 }), 100 + 490 - 400);
});

test('revealing an item above the sidebar viewport scrolls only the sidebar', () => {
    assert.equal(reveal({ top: 40, bottom: 70 }), 100 + 40 - 80);
});

test('an already visible navigation item does not change scroll or the Tab starting point', () => {
    assert.equal(reveal({ top: 100, bottom: 130 }), 100);
});

test('an absent current navigation item leaves the sidebar unchanged', () => {
    assert.equal(reveal(null), 100);
});
