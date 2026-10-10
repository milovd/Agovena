export function registerPageEditorComponents(Alpine) {
    Alpine.data('agPageEditor', () => ({
        mode: 'visual',
        content: '',
        editor: null,
        disposed: false,
        async init() {
            this.content = this.$root.dataset.initialBody ?? '';
            this.$wire.set('body', this.content, false);
            const { Editor, pageEditorExtensions } = await import('./page-editor-runtime.js');
            if (this.disposed) return;
            this.editor = new Editor({
                element: this.$refs.canvas,
                extensions: pageEditorExtensions(),
                content: this.content,
                editorProps: { attributes: { 'aria-labelledby': 'page-editor-label' } },
                onUpdate: ({ editor }) => {
                    this.content = editor.getHTML();
                    this.$wire.set('body', this.content, false);
                },
            });
        },
        destroy() {
            this.disposed = true;
            Alpine.raw(this.editor)?.destroy();
        },
        toggleSource() {
            const editor = Alpine.raw(this.editor);
            if (this.mode === 'html') {
                editor.commands.setContent(this.content);
                this.content = editor.getHTML();
                this.$wire.set('body', this.content, false);
                this.mode = 'visual';
            } else {
                this.content = editor.getHTML();
                this.mode = 'html';
            }
        },
        updateSource() {
            this.$wire.set('body', this.content, false);
        },
        toggleHeading(level) {
            Alpine.raw(this.editor).chain().focus().toggleHeading({ level }).run();
        },
        toggleBold() {
            Alpine.raw(this.editor).chain().focus().toggleBold().run();
        },
        toggleItalic() {
            Alpine.raw(this.editor).chain().focus().toggleItalic().run();
        },
        toggleList(type) {
            const editor = Alpine.raw(this.editor);
            if (type === 'ordered') editor.chain().focus().toggleOrderedList().run();
            else editor.chain().focus().toggleBulletList().run();
        },
        setLink() {
            const url = window.prompt(this.$root.dataset.linkPrompt);
            if (!url) return;
            const newTab = window.confirm(this.$root.dataset.linkNewTabPrompt);
            Alpine.raw(this.editor).chain().focus().setLink({ href: url, target: newTab ? '_blank' : null }).run();
        },
        insertImage(url, alt) {
            const escapeAttribute = (value) => value.replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            })[char]);
            const isStored = /^\/storage\/pages\/[A-Za-z0-9]{40}\.(?:jpg|jpeg|png|webp|gif)$/.test(url);
            let isPreview = false;
            if (!isStored && typeof window !== 'undefined') {
                try {
                    const preview = new URL(url, window.location.origin);
                    isPreview = preview.origin === window.location.origin
                        && /^\/livewire(?:-[a-f0-9]+)?\/preview-file\/[^/?#]+$/.test(preview.pathname)
                        && preview.searchParams.has('expires') && preview.searchParams.has('signature');
                } catch { /* Ignore malformed image sources. */ }
            }
            if ((!isStored && !isPreview) || !alt) return;
            if (this.mode === 'html') {
                this.content += `<img src="${escapeAttribute(url)}" alt="${escapeAttribute(alt)}">`;
                this.$wire.set('body', this.content, false);
                return;
            }
            Alpine.raw(this.editor).chain().focus().setImage({ src: url, alt }).run();
        },
    }));
}
