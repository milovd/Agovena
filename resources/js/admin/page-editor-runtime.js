import { Editor } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import StarterKit from '@tiptap/starter-kit';

export { Editor };

export function pageEditorExtensions() {
    return [StarterKit.configure({
        heading: { levels: [2, 3, 4] },
        code: false,
        codeBlock: false,
        horizontalRule: false,
        strike: false,
        underline: false,
        link: { openOnClick: false, autolink: false, defaultProtocol: 'https' },
    }), Image.configure({ allowBase64: false })];
}
