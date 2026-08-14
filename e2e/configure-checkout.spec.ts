import { test, expect } from '@playwright/test';
import { addProductToCart, continueCheckout, fillCheckoutDetails, placeOrder } from './helpers';

test('configurable checkout walks details configure and payment', async ({ page }) => {
    await addProductToCart(page, 'e2e-vps', { os: 'ubuntu' });
    await page.goto('/checkout');

    await expect(page.getByText('Configure', { exact: true }).first()).toBeVisible();
    await fillCheckoutDetails(page);
    await continueCheckout(page);

    await expect(page.getByRole('heading', { name: /Configure|Configuration/ })).toBeVisible();
    await expect(page.getByText('E2E Nova VPS').first()).toBeVisible();
    await expect(page.getByText('Ubuntu').first()).toBeVisible();
    await expect(page.getByText('pterodactyl', { exact: false })).toHaveCount(0);
    await continueCheckout(page);

    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    await placeOrder(page);
    await expect(page).toHaveURL(/\/orders\//);
});
