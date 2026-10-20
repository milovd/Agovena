import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    AREA_FILL_OPACITY,
    AREA_GRADIENT_BOTTOM,
    AREA_GRADIENT_TOP,
    BAR_RADIUS,
    areaGradient,
    interactiveDataset,
    parseIsoDate,
    registerChartComponents,
    withAlpha,
} from '../../resources/js/admin/chart.js';

const identity = (value) => value;

test('withAlpha converts hex and rgb token values and falls back to color-mix', () => {
    assert.equal(withAlpha('#155eef', 0.5), 'rgba(21, 94, 239, 0.5)');
    assert.equal(withAlpha('#fff', 0.1), 'rgba(255, 255, 255, 0.1)');
    assert.equal(withAlpha('rgb(21 94 239)', 0.2), 'rgba(21, 94, 239, 0.2)');
    assert.equal(withAlpha('rgba(1, 2, 3, 1)', 0.3), 'rgba(1, 2, 3, 0.3)');
    assert.equal(withAlpha('oklch(0.6 0.2 260)', 0.4), 'color-mix(in srgb, oklch(0.6 0.2 260) 40%, transparent)');
});

test('area datasets are monotone, 2px, dot-free until hover and filled with a vertical gradient', () => {
    const dataset = interactiveDataset(
        { label: 'Revenue', data: [0, 5, 2], borderColor: '#155eef', yAxisID: 'y', tension: 0, fill: false },
        { isBar: false, surface: '#ffffff', resolve: identity },
    );

    assert.equal(dataset.type, 'line');
    assert.equal(dataset.cubicInterpolationMode, 'monotone');
    assert.equal(dataset.borderWidth, 2);
    assert.equal(dataset.pointRadius, 0);
    assert.ok(dataset.pointHoverRadius > 0);
    assert.equal(dataset.pointHoverBorderColor, '#ffffff');
    assert.equal(dataset.fill, 'origin');
    assert.equal(dataset.yAxisID, 'y');
    assert.equal(typeof dataset.backgroundColor, 'function');
});

test('the area gradient runs from the top opacity to the bottom opacity inside the chart area', () => {
    const stops = [];
    const calls = [];
    const chart = {
        chartArea: { top: 10, bottom: 210 },
        ctx: {
            createLinearGradient(...args) {
                calls.push(args);

                return { addColorStop: (offset, color) => stops.push([offset, color]) };
            },
        },
    };
    const paint = areaGradient('#155eef');

    assert.equal(paint({ chart: {} }), withAlpha('#155eef', AREA_GRADIENT_BOTTOM * AREA_FILL_OPACITY));
    const gradient = paint({ chart });
    assert.equal(paint({ chart }), gradient, 'the gradient is cached for an unchanged chart area');
    assert.deepEqual(calls, [[0, 10, 0, 210]]);
    assert.deepEqual(stops, [
        [0, withAlpha('#155eef', AREA_GRADIENT_TOP * AREA_FILL_OPACITY)],
        [1, withAlpha('#155eef', AREA_GRADIENT_BOTTOM * AREA_FILL_OPACITY)],
    ]);
});

test('bar datasets are slim grouped bars with rounded top corners in the same series color', () => {
    const dataset = interactiveDataset(
        { label: 'Orders', data: [1, 2], borderColor: '#d97706', yAxisID: 'y1' },
        { isBar: true, surface: '#ffffff', resolve: identity },
    );

    assert.equal(dataset.type, 'bar');
    assert.equal(dataset.backgroundColor, '#d97706');
    assert.equal(dataset.borderRadius, BAR_RADIUS);
    assert.equal(dataset.borderSkipped, 'start');
    assert.ok(dataset.categoryPercentage < 1 && dataset.barPercentage < 1);
    assert.equal(dataset.borderWidth, 0);
});

test('ISO dates from the server parse as local calendar days', () => {
    const date = parseIsoDate('2026-01-31');

    assert.equal(date.getFullYear(), 2026);
    assert.equal(date.getMonth(), 0);
    assert.equal(date.getDate(), 31);
    assert.equal(parseIsoDate('Jan 31'), null);
});

test('the chart component disconnects its theme observer and pending frame on destroy', () => {
    const factories = new Map();
    registerChartComponents({ data(name, factory) { factories.set(name, factory); } });
    const component = factories.get('agChart')({});
    let disconnected = false;
    let cancelled = null;
    let destroyed = false;
    const previousWindow = globalThis.window;
    globalThis.window = { cancelAnimationFrame(id) { cancelled = id; } };

    try {
        component.themeObserver = { disconnect() { disconnected = true; } };
        component.themeFrame = 7;
        component.chart = { destroy() { destroyed = true; } };
        component.destroy();
    } finally {
        globalThis.window = previousWindow;
    }

    assert.equal(disconnected, true);
    assert.equal(cancelled, 7);
    assert.equal(destroyed, true);
    assert.equal(component.themeObserver, null);
    assert.equal(component.chart, null);
});

test('interactive datasets drop the server bar and point colors so Chart.js cannot pick up an unresolved var()', () => {
    const server = {
        label: 'Paid revenue',
        data: [1, 2],
        borderColor: 'var(--ag-color-chart-1)',
        backgroundColor: 'var(--ag-color-chart-1)',
        barBackgroundColor: 'var(--ag-color-chart-1)',
        barBorderColor: 'var(--ag-color-chart-1)',
        pointBackgroundColor: 'var(--ag-color-chart-1)',
        pointBorderColor: 'var(--ag-color-chart-1)',
        pointHoverBackgroundColor: 'var(--ag-color-chart-1)',
        pointHoverBorderColor: 'var(--ag-color-chart-1)',
    };
    const resolve = (value) => (value === 'var(--ag-color-chart-1)' ? '#155eef' : value);

    for (const isBar of [true, false]) {
        const dataset = interactiveDataset(server, { isBar, surface: '#ffffff', resolve });
        const unresolved = Object.entries(dataset).filter(([, value]) => typeof value === 'string' && value.includes('var('));

        assert.deepEqual(unresolved, [], isBar ? 'bar' : 'area');
    }
});
