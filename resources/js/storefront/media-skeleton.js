/*
 * Image skeletons: a media frame pulses only while its image is still downloading. Images that are
 * already decoded (cache, eager) never get the marker, so nothing flashes on fast loads.
 */
const MARKER = 'data-skeleton-loading';

function release(image) {
    image.parentElement?.removeAttribute(MARKER);
}

function markPending(root = document) {
    root.querySelectorAll('img[loading="lazy"]').forEach((image) => {
        if (!image.complete && image.parentElement) {
            image.parentElement.setAttribute(MARKER, '');
        }
    });
}

export function initMediaSkeletons() {
    const settle = (event) => {
        if (event.target instanceof HTMLImageElement) {
            release(event.target);
        }
    };

    // load and error do not bubble; capture them once for every image on the page.
    document.addEventListener('load', settle, true);
    document.addEventListener('error', settle, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => markPending(), { once: true });
    } else {
        markPending();
    }

    document.addEventListener('livewire:navigated', () => markPending());
}
