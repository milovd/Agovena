import { test, expect } from '@playwright/test';
import { addProductToCart, continueCheckout, fillCheckoutDetails, guest } from './helpers';

test('mixed physical and configured service checkout skips duplicate configuration', async ({ page }) => {
    await addProductToCart(page, 'e2e-physical');
    await addProductToCart(page, 'e2e-vps', { os: 'ubuntu' });
    await page.goto('/checkout');
    await expect(page.getByTestId('checkout-stepper').getByText('Configure', { exact: true })).toHaveCount(0);

    await expect(page.getByTestId('checkout-stepper').getByText('Delivery')).toBeVisible();
    await fillCheckoutDetails(page, { ...guest, email: `mixed-${Date.now()}@example.test` });
    await continueCheckout(page);

    await expect(page.getByRole('radio').first()).toBeVisible();
    await expect(page.getByText('E2E Nova VPS').first()).toBeVisible();
    await page.getByRole('radio').first().check();
    await continueCheckout(page);

    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    await expect(page.locator('.store-summary-line')).toHaveCount(2);
});
