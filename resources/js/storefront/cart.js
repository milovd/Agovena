/** Cart page line quantities (theme::cart.index). */
export function registerCartComponents(Alpine) {
    Alpine.data('storefrontCartQuantity', () => ({
        min: 1,
        max: 99,
        lineKey: '',
        value: 1,
        syncTimer: null,
        init() {
            this.min = Number(this.$root.dataset.min || 1);
            this.max = Number(this.$root.dataset.max || 99);
            this.lineKey = this.$root.dataset.lineKey || '';
            this.value = this.readValue();
        },
        increment() {
            this.setValue(this.value + 1);
            this.queueSync();
        },
        decrement() {
            this.setValue(this.value - 1);
            this.queueSync();
        },
        onInput() {
            this.value = this.readValue();
            this.queueSync();
        },
        readValue() {
            const value = Number(this.$refs.input?.value);

            return Number.isFinite(value)
                ? Math.min(this.max, Math.max(this.min, value))
                : this.min;
        },
        setValue(rawValue) {
            const input = this.$refs.input;
            const value = Math.min(this.max, Math.max(this.min, Math.trunc(Number(rawValue))));
            this.value = value;
            if (input && input.value !== String(value)) {
                input.value = String(value);
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
        queueSync() {
            window.clearTimeout(this.syncTimer);
            this.syncTimer = window.setTimeout(() => this.sync(), 300);
        },
        async sync() {
            const target = this.value;
            await this.$wire.setLineQuantity(this.lineKey, target);
            if (this.value !== target) {
                this.setValue(this.value);
                this.queueSync();
            }
        },
    }));
}
