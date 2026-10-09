export function registerPageEditorComponents(Alpine) {
    Alpine.data('agPageEditor', (initialBody) => ({
        mode: 'visual',
        content: initialBody,
        editor: null,
        disposed: false,
        async init() {
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
            this.editor?.destroy();
        },
        toggleSource() {
            if (this.mode === 'html') {
                this.editor.commands.setContent(this.content);
                this.content = this.editor.getHTML();
                this.$wire.set('body', this.content, false);
                this.mode = 'visual';
            } else {
                this.content = this.editor.getHTML();
                this.mode = 'html';
            }
        },
        updateSource() {
            this.$wire.set('body', this.content, false);
        },
        toggleHeading(level) {
            this.editor.chain().focus().toggleHeading({ level }).run();
        },
        toggleBold() {
            this.editor.chain().focus().toggleBold().run();
        },
        toggleItalic() {
            this.editor.chain().focus().toggleItalic().run();
        },
        toggleList(type) {
            if (type === 'ordered') this.editor.chain().focus().toggleOrderedList().run();
            else this.editor.chain().focus().toggleBulletList().run();
        },
        setLink() {
            const url = window.prompt(this.$root.dataset.linkPrompt);
            if (!url) return;
            const newTab = window.confirm(this.$root.dataset.linkNewTabPrompt);
            this.editor.chain().focus().setLink({ href: url, target: newTab ? '_blank' : null }).run();
        },
        insertImage(url, alt) {
            if (!/^\/storage\/pages\/[A-Za-z0-9]{40}\.(?:jpg|jpeg|png|webp|gif)$/.test(url) || !alt) return;
            if (this.mode === 'html') {
                const escapedAlt = alt.replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                })[char]);
                this.content += `<img src="${url}" alt="${escapedAlt}">`;
                this.$wire.set('body', this.content, false);
                return;
            }
            this.editor.chain().focus().setImage({ src: url, alt }).run();
        },
    }));
}
