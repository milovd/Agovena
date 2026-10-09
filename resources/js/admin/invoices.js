/*
 * Invoice design page: scales the A4 preview frame to the width of its column.
 * The Admin runs the Alpine CSP build, so the frame is read here instead of through directives on it,
 * and Livewire replaces the frame on every change (it is keyed on its content).
 */
const A4_WIDTH = 794;
const A4_HEIGHT = 1123;

export function registerInvoiceComponents(Alpine) {
    Alpine.data('agInvoicePreview', () => ({
        scale: 1,
        height: A4_HEIGHT,
        observer: null,

        init() {
            this.observer = new ResizeObserver(() => this.fit());
            this.observer.observe(this.$el);
            // Load does not bubble; capture it so a replaced frame is measured too.
            this.$el.addEventListener('load', () => this.measure(), true);
            this.fit();
            this.measure();
        },

        destroy() {
            this.observer?.disconnect();
        },

        get paperStyle() {
            return { height: Math.ceil(this.height * this.scale) + 'px' };
        },

        frame() {
            return this.$el.querySelector('iframe');
        },

        fit() {
            const width = this.$el.clientWidth;
            this.scale = width > 0 ? Math.min(1, width / A4_WIDTH) : 1;
            this.apply();
        },

        measure() {
            const doc = this.frame()?.contentDocument;
            if (doc?.documentElement) {
                this.height = Math.max(A4_HEIGHT, doc.documentElement.scrollHeight);
            }
            this.apply();
        },

        apply() {
            const frame = this.frame();
            if (frame) {
                frame.style.transform = 'scale(' + this.scale + ')';
                frame.style.height = this.height + 'px';
            }
        },
    }));
}
