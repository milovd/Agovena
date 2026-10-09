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

test('the editor exposes body headings only, not title-level headings or unapproved code styles', () => {
    const extensions = pageEditorExtensions();
    const starter = extensions.find((extension) => extension.name === 'starterKit');
    assert.deepEqual(starter.options.heading.levels, [2, 3, 4]);
    assert.equal(starter.options.code, false);
    assert.equal(starter.options.codeBlock, false);
    assert.ok(extensions.some((extension) => extension.name === 'image'));
});
