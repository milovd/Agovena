import { test, expect } from '@playwright/test';
import { addProductToCart, assertImageNotBroken } from './helpers';

test('storefront logo and images never render as broken', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.store-brand__logo').first()).toBeVisible();
    await assertImageNotBroken(page.locator('img'));

    await addProductToCart(page, 'e2e-digital');
    await page.goto('/cart');
    await expect(page.locator('.store-cart-line')).toHaveCount(1);
    await expect(page.locator('.store-cart-line__media .store-product-card__placeholder, .store-cart-line__media img')).toBeVisible();
    await assertImageNotBroken(page.locator('img'));
});
