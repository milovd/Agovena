import { test, expect } from '@playwright/test';
import { addProductToCart, continueCheckout, fillCheckoutDetails } from './helpers';

test('configurable product is configured before checkout without a duplicate configuration step', async ({ page }) => {
    await addProductToCart(page, 'e2e-vps', { os: 'ubuntu' });
    await page.goto('/checkout');

    await expect(page.getByTestId('checkout-stepper').getByText('Configure', { exact: true })).toHaveCount(0);
    await expect(page.getByText('E2E Nova VPS').first()).toBeVisible();
    await expect(page.getByText('Operating system: Ubuntu', { exact: false })).toBeVisible();
    await expect(page.getByText('pterodactyl', { exact: false })).toHaveCount(0);

    await fillCheckoutDetails(page);
    await continueCheckout(page);
    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
});
