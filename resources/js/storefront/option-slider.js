/**
 * Product option slider (theme::partials.product-options, type "slider").
 *
 * The native range input works on stop indexes (0..n-1); the Livewire property
 * optionSelections.<key> holds the chosen stop value. The server renders the
 * initial index, fill and labels; this component only maps index -> value,
 * paints the fill/labels while dragging and commits on change. After a
 * Livewire morph it re-reads data-index so the thumb follows the server value.
 */

/** Filled share of the track in percent for a stop index. */
export function sliderFill(index, max) {
    const last = Number(max);
    if (!Number.isFinite(last) || last <= 0) return 0;
    const clamped = Math.min(last, Math.max(0, Math.trunc(Number(index) || 0)));

    return Math.round((clamped / last) * 1000000) / 10000;
}

/** Parses the server rendered stop list; invalid markup yields no stops. */
export function parseStops(raw) {
    try {
        const stops = JSON.parse(raw || '[]');

        return Array.isArray(stops) ? stops.filter((stop) => stop && typeof stop.value === 'string') : [];
    } catch {
        return [];
    }
}

/** Clamps a raw index to the available stops. */
export function stopIndex(raw, count) {
    const index = Math.trunc(Number(raw));
    if (!Number.isFinite(index) || count <= 0) return 0;

    return Math.min(count - 1, Math.max(0, index));
}

export function registerOptionSliderComponents(Alpine) {
    Alpine.data('storefrontOptionSlider', () => ({
        pending: 0,
        observer: null,
        init() {
            const input = this.$refs.input;
            if (!input) return;
            if (typeof MutationObserver !== 'undefined') {
                this.observer = new MutationObserver(() => this.sync());
                this.observer.observe(input, { attributes: true, attributeFilter: ['data-index', 'max'] });
            }
            this.sync();
        },
        destroy() {
            this.observer?.disconnect();
        },
        stops() {
            return parseStops(this.$root.dataset.stops);
        },
        currentIndex() {
            return stopIndex(this.$refs.input?.value, this.stops().length);
        },
        /** Server value wins once no local change is in flight. */
        sync() {
            const input = this.$refs.input;
            if (!input || this.pending > 0) return;
            const index = stopIndex(input.dataset.index, this.stops().length);
            if (Number(input.value) !== index) input.value = String(index);
            this.paint(index);
        },
        preview() {
            this.paint(this.currentIndex());
        },
        async commit() {
            const index = this.currentIndex();
            const stop = this.stops()[index];
            const key = this.$root.dataset.optionKey;
            if (!stop || !key) return;
            this.paint(index);
            this.pending += 1;
            try {
                await this.$wire.set(`optionSelections.${key}`, stop.value);
            } finally {
                this.pending = Math.max(0, this.pending - 1);
                this.sync();
            }
        },
        pick(event) {
            const input = this.$refs.input;
            const raw = event?.currentTarget?.dataset?.index;
            if (!input || input.disabled || raw === undefined) return;
            input.value = String(stopIndex(raw, this.stops().length));
            input.focus({ preventScroll: true });
            this.commit();
        },
        paint(index) {
            const input = this.$refs.input;
            const stop = this.stops()[index];
            if (!input || !stop) return;
            input.style.setProperty('--fill', `${sliderFill(index, input.max)}%`);
            input.setAttribute('aria-valuetext', stop.label);
            const output = this.$root.closest('.store-field')?.querySelector('[data-slider-output]');
            if (output) output.textContent = stop.label;
            this.$root.querySelectorAll('.store-slider__tick').forEach((tick) => {
                tick.classList.toggle('is-active', Number(tick.dataset.index) === index);
            });
        },
    }));
}
