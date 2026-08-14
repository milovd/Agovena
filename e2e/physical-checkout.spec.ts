import { test, expect } from '@playwright/test';
import { addProductToCart, continueCheckout, fillCheckoutDetails, placeOrder } from './helpers';

test('physical checkout walks details delivery and payment', async ({ page }) => {
    await addProductToCart(page, 'e2e-physical');
    await page.goto('/checkout');

    await expect(page.getByText('Delivery', { exact: true }).first()).toBeVisible();
    await fillCheckoutDetails(page);
    await continueCheckout(page);

    await expect(page.getByRole('heading', { name: 'Shipping address' })).toBeVisible();
    await expect(page.locator('.store-choice--row').first()).toBeVisible();
    await page.locator('.store-choice--row input[type="radio"]').first().check();
    await continueCheckout(page);

    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    await placeOrder(page);
    await expect(page).toHaveURL(/\/orders\//);
});
