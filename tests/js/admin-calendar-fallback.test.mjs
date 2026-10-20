import assert from 'node:assert/strict';
import { test } from 'node:test';
import { registerCalendar } from '../../resources/js/admin/calendar.js';

function register(supported) {
    const previousDocument = globalThis.document;
    const previousWindow = globalThis.window;
    const classes = [];
    const listeners = [];
    globalThis.document = {
        documentElement: { classList: { add(name) { classes.push(name); } } },
        addEventListener(name) { listeners.push(name); },
    };
    globalThis.window = supported === undefined ? {} : {
        CSS: {
            supports(selector) {
                assert.equal(selector, 'selector(input::-webkit-calendar-picker-indicator)');

                return supported;
            },
        },
    };

    try {
        registerCalendar();
        registerCalendar();

        return { classes, listeners };
    } finally {
        if (previousDocument === undefined) delete globalThis.document;
        else globalThis.document = previousDocument;
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
}

test('browsers without a hideable native date indicator retain one native picker', () => {
    assert.deepEqual(register(false), { classes: [], listeners: [] });
});

test('browsers without selector feature detection keep the native fallback', () => {
    assert.deepEqual(register(undefined), { classes: [], listeners: [] });
});

test('supported browsers register the enhanced calendar only once', () => {
    assert.deepEqual(register(true), {
        classes: ['ag-calendar-ready'],
        listeners: ['click', 'pointerdown', 'livewire:navigating', 'livewire:navigated'],
    });
});
