import assert from 'node:assert/strict';
import { test } from 'node:test';
import { registerPageEditorComponents } from '../../resources/js/admin/page-editor.js';

function mountReactiveEditor() {
    let factory;
    const calls = [];
    const rawEditor = {
        commands: { setContent(value) { calls.push(['setContent', value]); } },
        getHTML() { return '<p>Visual QA note</p>'; },
        chain() {
            return {
                focus() { return this; },
                toggleBold() { return this; },
                run() { calls.push(['toggleBold']); },
            };
        },
    };
    const reactiveEditor = new Proxy(rawEditor, {
        get(target, key) {
            if (key === 'commands' || key === 'chain') {
                throw new RangeError('Applying a mismatched transaction');
            }
            return Reflect.get(target, key);
        },
    });
    const Alpine = {
        data(_name, callback) { factory = callback; },
        raw(value) { return value === reactiveEditor ? rawEditor : value; },
    };
    registerPageEditorComponents(Alpine);
    const component = factory();
    component.editor = reactiveEditor;
    component.$wire = { set(...args) { calls.push(['wire', ...args]); } };
    return { component, calls };
}

test('HTML to visual uses the unwrapped Tiptap editor instead of an Alpine proxy', () => {
    const { component, calls } = mountReactiveEditor();
    component.mode = 'html';
    component.content = '<p>Visual QA note</p>';

    component.toggleSource();

    assert.equal(component.mode, 'visual');
    assert.deepEqual(calls, [
        ['setContent', '<p>Visual QA note</p>'],
        ['wire', 'body', '<p>Visual QA note</p>', false],
    ]);
});

test('formatting commands use the unwrapped Tiptap editor', () => {
    const { component, calls } = mountReactiveEditor();

    component.toggleBold();

    assert.deepEqual(calls, [['toggleBold']]);
});
