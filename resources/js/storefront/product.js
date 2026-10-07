/** Product detail page: quantity stepper, gallery and detail panels (theme::catalog.*). */
export function registerProductComponents(Alpine) {
    Alpine.data('storefrontQuantity', () => ({
        min: 1,
        max: 99,
        init() {
            this.min = Number(this.$root.dataset.min || 1);
            this.max = Number(this.$root.dataset.max || 99);
        },
        increment() {
            this.setValue(this.readValue() + 1);
        },
        decrement() {
            this.setValue(this.readValue() - 1);
        },
        normalize() {
            this.setValue(this.readValue());
        },
        readValue() {
            const value = Number(this.$refs.input?.value);

            return Number.isFinite(value) ? value : this.min;
        },
        setValue(rawValue) {
            const input = this.$refs.input;
            if (!input) return;

            const value = Math.min(this.max, Math.max(this.min, Math.trunc(Number(rawValue))));
            input.value = String(value);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }));

    Alpine.data('storefrontProductGallery', () => ({
        images: [],
        index: 0,
        thumbsOverflow: false,
        canScrollLeft: false,
        canScrollRight: false,
        select(index) {
            this.index = index;
            this.$nextTick(() => {
                const thumb = this.$refs.track?.querySelector(`[data-index='${this.index}']`);
                thumb?.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
                this.updateScrollState();
            });
        },
        currentImage() {
            return this.images[this.index] || this.images[0] || '';
        },
        thumbClass(index) {
            return { 'is-active': this.index === index };
        },
        thumbAriaCurrent(index) {
            return this.index === index ? 'true' : 'false';
        },
        arrowClass(canScroll) {
            return { 'is-disabled': !canScroll };
        },
        arrowDisabled(canScroll) {
            return !canScroll;
        },
        arrowAriaHidden(canScroll) {
            return (!canScroll).toString();
        },
        scrollThumbs(direction) {
            const track = this.$refs.track;
            if (!track) return;
            const styles = getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap) || 12;
            const size = parseFloat(styles.getPropertyValue('--thumb-size')) || 72;
            const step = Math.max(size + gap, track.clientWidth * 0.85);
            track.scrollBy({ left: direction * step, behavior: 'smooth' });
        },
        layoutThumbs() {
            const track = this.$refs.track;
            if (!track) return;
            const styles = getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap) || 12;
            const pad = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0);
            const inner = Math.max(0, track.clientWidth - pad);
            const count = track.children.length;
            const slots = Math.max(1, Math.floor((inner + gap) / (64 + gap)));
            if (count > slots && inner > 0) {
                const size = (inner - ((slots - 1) * gap)) / slots;
                track.style.setProperty('--thumb-size', `${size}px`);
                track.classList.add('is-fill');
            } else {
                track.style.removeProperty('--thumb-size');
                track.classList.remove('is-fill');
            }
            this.updateScrollState();
        },
        updateScrollState() {
            const track = this.$refs.track;
            if (!track) {
                this.thumbsOverflow = false;
                this.canScrollLeft = false;
                this.canScrollRight = false;
                return;
            }
            const max = track.scrollWidth - track.clientWidth;
            this.thumbsOverflow = max > 4;
            this.canScrollLeft = this.thumbsOverflow && track.scrollLeft > 4;
            this.canScrollRight = this.thumbsOverflow && track.scrollLeft < max - 4;
        },
        init() {
            this.images = JSON.parse(this.$root.dataset.images || '[]');
            this.$nextTick(() => {
                this.layoutThumbs();
                const track = this.$refs.track;
                if (!track) return;
                track.addEventListener('scroll', () => this.updateScrollState(), { passive: true });
                window.addEventListener('resize', () => this.layoutThumbs());
                if (typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(() => this.layoutThumbs()).observe(track);
                }
            });
        },
    }));

    Alpine.data('storefrontProductPanels', () => ({
        tab: 'details',
        reviewsOn: false,
        init() {
            this.reviewsOn = this.$root.dataset.reviewsOn === 'true';
            this.tab = window.location.hash === '#reviews' && this.reviewsOn
                ? 'reviews'
                : this.$root.dataset.defaultTab;
        },
        openReviews() {
            if (!this.reviewsOn) return;
            this.tab = 'reviews';
            history.replaceState(null, '', '#reviews');
            this.$nextTick(() => this.$refs.reviews?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
        selectTab(tab) {
            this.tab = tab;
            history.replaceState(null, '', `#${tab}`);
        },
    }));
}
