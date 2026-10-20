import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    parseStops,
    registerOptionSliderComponents,
    sliderFill,
    stopIndex,
} from '../../resources/js/storefront/option-slider.js';

test('slider fill maps stop indexes to a track percentage', () => {
    assert.equal(sliderFill(0, 2), 0);
    assert.equal(sliderFill(1, 2), 50);
    assert.equal(sliderFill(2, 2), 100);
    assert.equal(sliderFill(1, 3), 33.3333);
    assert.equal(sliderFill(5, 2), 100);
    assert.equal(sliderFill(0, 0), 0);
});

test('stop parsing and index clamping tolerate bad markup', () => {
    assert.deepEqual(parseStops('not json'), []);
    assert.deepEqual(parseStops('[{"value":"2","label":"2 GB"},{"label":"broken"}]'), [{ value: '2', label: '2 GB' }]);
    assert.equal(stopIndex('7', 3), 2);
    assert.equal(stopIndex('-1', 3), 0);
    assert.equal(stopIndex('abc', 3), 0);
});

function fakeSlider({ index = 0, stops, serverAccepts = true }) {
    let factory;
    registerOptionSliderComponents({ data: (name, fn) => { if (name === 'storefrontOptionSlider') factory = fn; } });
    const style = {};
    const attributes = {};
    const input = {
        value: String(index),
        max: String(stops.length - 1),
        disabled: false,
        dataset: { index: String(index) },
        style: { setProperty: (name, value) => { style[name] = value; } },
        setAttribute: (name, value) => { attributes[name] = value; },
        focus: () => {},
    };
    const output = { textContent: '' };
    const ticks = stops.map((_, i) => ({ dataset: { index: String(i) }, active: false, classList: { toggle(_name, on) { ticks[i].active = on; } } }));
    const sets = [];
    const component = factory();
    Object.assign(component, {
        $refs: { input },
        $root: {
            dataset: { optionKey: 'ram', stops: JSON.stringify(stops) },
            closest: () => ({ querySelector: () => output }),
            querySelectorAll: () => ticks,
        },
        $wire: {
            set: async (name, value) => {
                sets.push([name, value]);
                // Livewire morphs the re-rendered data-index before the promise resolves.
                if (serverAccepts) input.dataset.index = String(stops.findIndex((stop) => stop.value === value));
            },
        },
    });

    return { component, input, style, attributes, output, ticks, sets };
}

test('committing a stop sets the mapped choice value on Livewire and paints the slider', async () => {
    const stops = [{ value: '2', label: '2 GB' }, { value: '4', label: '4 GB' }, { value: '8', label: '8 GB' }];
    const slider = fakeSlider({ stops });
    slider.component.init();
    assert.equal(slider.style['--fill'], '0%');

    slider.input.value = '2';
    await slider.component.commit();

    assert.deepEqual(slider.sets, [['optionSelections.ram', '8']]);
    assert.equal(slider.attributes['aria-valuetext'], '8 GB');
    assert.equal(slider.output.textContent, '8 GB');
    assert.equal(slider.ticks[2].active, true);
});

test('the thumb returns to the server value when the server keeps the old stop', async () => {
    const stops = [{ value: '2', label: '2 GB' }, { value: '4', label: '4 GB' }];
    const slider = fakeSlider({ stops, serverAccepts: false });
    slider.input.value = '1';
    await slider.component.commit();

    assert.equal(slider.input.value, '0');
    assert.equal(slider.attributes['aria-valuetext'], '2 GB');
});

test('a server morph moves the thumb to the rendered index', () => {
    const stops = [{ value: '2', label: '2 GB' }, { value: '4', label: '4 GB' }];
    const slider = fakeSlider({ stops });
    slider.input.dataset.index = '1';
    slider.component.sync();

    assert.equal(slider.input.value, '1');
    assert.equal(slider.style['--fill'], '100%');
    assert.equal(slider.attributes['aria-valuetext'], '4 GB');
});
