/*
 * Storefront entry (loaded by the theme layouts via @vite). Alpine components
 * live per domain under ./storefront/; this file only wires them up.
 */
import { registerAccountComponents } from './storefront/account.js';
import { registerCartComponents } from './storefront/cart.js';
import { registerCheckoutComponents } from './storefront/checkout.js';
import { registerChromeComponents } from './storefront/chrome.js';
import { initCookieConsent } from './storefront/consent.js';
import { registerHeaderComponents } from './storefront/header.js';
import { registerHomeComponents } from './storefront/home.js';
import { registerProductComponents } from './storefront/product.js';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    registerHomeComponents(Alpine);
    registerChromeComponents(Alpine);
    registerHeaderComponents(Alpine);
    registerProductComponents(Alpine);
    registerCartComponents(Alpine);
    registerCheckoutComponents(Alpine);
    registerAccountComponents(Alpine);
});

initCookieConsent();
