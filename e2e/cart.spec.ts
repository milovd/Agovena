import { test, expect } from '@playwright/test';
import { addProductToCart, assertImageNotBroken, assertNoHorizontalScroll } from './helpers';

test.describe('cart', () => {
    test('quantity stepper and remove update the cart', async ({ page }) => {
        await addProductToCart(page, 'e2e-digital');
        await page.goto('/cart');
        await expect(page.getByRole('heading', { name: 'Cart' })).toBeVisible();
        await expect(page.locator('.store-cart-line')).toHaveCount(1);
        await assertImageNotBroken(page.locator('.store-cart-line img, .store-brand__logo'));
        await assertNoHorizontalScroll(page);

        await page.getByRole('button', { name: 'Increase quantity' }).click();
        await expect(page.locator('.store-qty__input')).toHaveValue('2');

        await page.getByRole('button', { name: 'Decrease quantity' }).click();
        await expect(page.locator('.store-qty__input')).toHaveValue('1');

        await page.getByRole('button', { name: /Remove / }).click();
        await expect(page.getByText('Your cart is empty')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Continue shopping' })).toBeVisible();
    });

    test('quantity stepper updates immediately while Livewire is processing', async ({ page }) => {
        await addProductToCart(page, 'e2e-digital');
        await page.goto('/cart');
        await expect(page.locator('.store-qty__input')).toHaveValue('1');

        await page.route('**/livewire/update', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 750));
            await route.continue();
        });

        await page.getByRole('button', { name: 'Increase quantity' }).click();
        await expect(page.locator('.store-qty__input')).toHaveValue('2', { timeout: 300 });
        await page.unroute('**/livewire/update');
    });

    test('quantity stepper preserves rapid clicks while Livewire is processing', async ({ page }) => {
        await addProductToCart(page, 'e2e-digital');
        await page.goto('/cart');
        await expect(page.locator('.store-qty__input')).toHaveValue('1');

        await page.route('**/livewire/update', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500));
            await route.continue();
        });

        const increase = page.getByRole('button', { name: 'Increase quantity' });
        const box = await increase.boundingBox();
        expect(box).not.toBeNull();
        for (let click = 0; click < 3; click++) {
            await page.mouse.click(box!.x + box!.width / 2, box!.y + box!.height / 2);
            await page.waitForTimeout(40);
        }
        await expect(page.locator('.store-qty__input')).toHaveValue('4');
        await page.waitForTimeout(1200);
        await expect(page.locator('.store-qty__input')).toHaveValue('4');
        await page.unroute('**/livewire/update');
    });

    test('checkout CTA opens checkout with storefront chrome', async ({ page }) => {
        await addProductToCart(page, 'e2e-digital');
        await page.goto('/cart');
        await page.getByRole('link', { name: 'Checkout' }).click();
        await expect(page).toHaveURL(/\/checkout/);
        await expect(page.locator('.store-chrome')).toBeVisible();
        await expect(page.locator('.store-chrome--reduced')).toHaveCount(0);
        await expect(page.getByRole('search').first()).toBeVisible();
        await expect(page.getByRole('link', { name: /Cart, \d+ items?/ })).toBeVisible();
        await expect(page.locator('.store-brand__logo')).toBeVisible();
        await assertImageNotBroken(page.locator('.store-brand__logo'));
    });
});
