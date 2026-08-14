import { test } from '@playwright/test';
import { addProductToCart, assertNoHorizontalScroll, fillCheckoutDetails } from './helpers';

const widths = [1440, 1280, 1024, 768, 430, 390, 360];

test('cart and checkout do not scroll horizontally at key widths', async ({ page }) => {
    await addProductToCart(page, 'e2e-physical');
    await page.goto('/cart');

    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        await assertNoHorizontalScroll(page);
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/checkout');
    await fillCheckoutDetails(page);

    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        await assertNoHorizontalScroll(page);
    }
});
