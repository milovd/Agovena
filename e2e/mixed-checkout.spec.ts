import { test, expect } from '@playwright/test';
import { addProductToCart, continueCheckout, fillCheckoutDetails, guest, placeOrder } from './helpers';

test('mixed cart composes delivery and configure then payment', async ({ page }) => {
    await addProductToCart(page, 'e2e-physical');
    await addProductToCart(page, 'e2e-digital');
    await addProductToCart(page, 'e2e-vps', { os: 'ubuntu' });
    await addProductToCart(page, 'e2e-ticket');
    await page.goto('/checkout');

    await expect(page.locator('.store-stepper__label').filter({ hasText: 'Delivery & configure' })).toBeVisible();
    await fillCheckoutDetails(page, { ...guest, email: `mixed-${Date.now()}@example.test` });
    await continueCheckout(page);

    await expect(page.locator('.store-choice--row').first()).toBeVisible();
    await expect(page.locator('.store-checkout__config-name').filter({ hasText: 'E2E Nova VPS' })).toBeVisible();
    await page.locator('.store-choice--row input[type="radio"]').first().check();
    await continueCheckout(page);

    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    await expect(page.locator('.store-summary-line')).toHaveCount(4);
    await placeOrder(page);
    await expect(page).toHaveURL(/\/orders\//);
    await expect(page.getByRole('heading').first()).toBeVisible();
});
