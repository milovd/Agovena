import { test, expect } from '@playwright/test';
import { addProductToCart, assertImageNotBroken } from './helpers';

test('storefront logo and images never render as broken', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.store-brand__logo').first()).toBeVisible();
    await assertImageNotBroken(page.locator('img'));

    const srcs = await page.locator('img').evaluateAll((images) =>
        images
            .filter((image) => {
                const box = image.getBoundingClientRect();

                return box.width >= 2 && box.height >= 2;
            })
            .map((image) => (image as HTMLImageElement).getAttribute('src') ?? ''),
    );
    for (const src of srcs) {
        expect(src, src).not.toMatch(/^https?:\/\/(127\.0\.0\.1|localhost)\b/);
        expect(src, src).not.toMatch(/^https?:\/\/[^/]+\/storage\//);
    }

    await addProductToCart(page, 'e2e-digital');
    await page.goto('/cart');
    await expect(page.locator('.store-cart-line')).toHaveCount(1);
    await expect(page.locator('.store-cart-line__media')).toHaveCount(1);
    await assertImageNotBroken(page.locator('img'));
});
