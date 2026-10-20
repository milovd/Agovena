import assert from 'node:assert/strict';
import { test } from 'node:test';
import { registerBulkSelection } from '../../resources/js/admin/bulk-selection.js';

test('bulk menu follows the actual header height when the selection counter wraps on mobile', () => {
    let factory;
    let notify;
    let observed;
    let disconnected = false;
    let height = 55.5;
    const properties = new Map();
    const header = { getBoundingClientRect: () => ({ height }) };
    const root = {
        dataset: { bulkModel: 'selectedProductIds', bulkVisible: '[1,2]' },
        querySelector: (selector) => selector === 'thead tr' ? header : null,
        style: { setProperty: (key, value) => properties.set(key, value) },
    };
    const previous = globalThis.ResizeObserver;
    globalThis.ResizeObserver = class {
        constructor(callback) { notify = callback; }
        observe(element) { observed = element; }
        disconnect() { disconnected = true; }
    };

    try {
        registerBulkSelection({ data: (_name, value) => { factory = value; } });
        const component = factory();
        component.$el = root;
        component.init();

        assert.equal(properties.get('--ag-bulk-head-height'), '55.5px');
        assert.equal(observed, header);
        height = 44;
        notify();
        assert.equal(properties.get('--ag-bulk-head-height'), '44px');
        component.destroy();
        assert.equal(disconnected, true);
    } finally {
        if (previous === undefined) delete globalThis.ResizeObserver;
        else globalThis.ResizeObserver = previous;
    }
});
