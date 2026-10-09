import assert from 'node:assert/strict';
import test from 'node:test';

import { registerPageEditorComponents } from '../../resources/js/admin/page-editor.js';
import { pageEditorExtensions } from '../../resources/js/admin/page-editor-runtime.js';

test('image insertion ignores non-local and uncaptioned sources', () => {
    let factory;
    registerPageEditorComponents({ data(name, callback) { if (name === 'agPageEditor') factory = callback; } });
    const state = factory('');
    const images = [];
    state.editor = { chain: () => ({ focus: () => ({ setImage: (image) => ({ run: () => images.push(image) }) }) }) };
    state.insertImage('https://other.example/image.png', 'External');
    state.insertImage('/storage/pages/' + 'a'.repeat(40) + '.png', '');
    state.insertImage('/storage/pages/' + 'a'.repeat(40) + '.png', 'Diagram');
    assert.deepEqual(images, [{ src: '/storage/pages/' + 'a'.repeat(40) + '.png', alt: 'Diagram' }]);
});

test('image insertion in HTML mode preserves unsaved source edits and escapes alt text', () => {
    let factory;
    registerPageEditorComponents({ data(name, callback) { if (name === 'agPageEditor') factory = callback; } });
    const state = factory('<p>old visual content</p>');
    const updates = [];
    let visualInsertion = 0;
    state.mode = 'html';
    state.content = '<p>new unsaved source</p>';
    state.$wire = { set(key, value) { updates.push([key, value]); } };
    state.editor = { chain: () => ({ focus: () => ({ setImage: () => ({ run: () => { visualInsertion++; state.content = '<p>old visual content</p>'; } }) }) }) };

    state.insertImage('/storage/pages/' + 'a'.repeat(40) + '.png', 'A "quoted" image');

    assert.equal(visualInsertion, 0);
    assert.equal(state.content, '<p>new unsaved source</p><img src="/storage/pages/' + 'a'.repeat(40) + '.png" alt="A &quot;quoted&quot; image">');
    assert.deepEqual(updates, [['body', state.content]]);
});

test('link control chooses current or new tab explicitly', () => {
    let factory;
    registerPageEditorComponents({ data(name, callback) { if (name === 'agPageEditor') factory = callback; } });
    const state = factory('');
    state.$root = { dataset: { linkPrompt: 'URL', linkNewTabPrompt: 'Open in new tab?' } };
    const links = [];
    state.editor = { chain: () => ({ focus: () => ({ setLink: (link) => ({ run: () => links.push(link) }) }) }) };
    const previousWindow = globalThis.window;
    try {
        globalThis.window = { prompt: () => 'https://example.test', confirm: () => false };
        state.setLink();
        globalThis.window.confirm = () => true;
        state.setLink();
    } finally {
        globalThis.window = previousWindow;
    }
    assert.deepEqual(links, [
        { href: 'https://example.test', target: null },
        { href: 'https://example.test', target: '_blank' },
    ]);
    assert.equal(pageEditorExtensions().find((extension) => extension.name === 'starterKit').options.link.HTMLAttributes.target, null);
});

test('the editor exposes body headings only, not title-level headings or unapproved code styles', () => {
    const extensions = pageEditorExtensions();
    const starter = extensions.find((extension) => extension.name === 'starterKit');
    assert.deepEqual(starter.options.heading.levels, [2, 3, 4]);
    assert.equal(starter.options.code, false);
    assert.equal(starter.options.codeBlock, false);
    assert.ok(extensions.some((extension) => extension.name === 'image'));
});
