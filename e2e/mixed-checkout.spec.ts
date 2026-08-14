import { test, expect } from '@playwright/test';
import { addProductToCart, continueCheckout, fillCheckoutDetails, guest, placeOrder } from './helpers';

test('mixed cart composes delivery and configure then payment', async ({ page }) => {
    await addProductToCart(page, 'e2e-physical');
    await addProductToCart(page, 'e2e-digital');
    await addProductToCart(page, 'e2e-vps', { os: 'ubuntu' });
    await addProductToCart(page, 'e2e-ticket');
    await page.goto('/checkout');

    await expect(page.getByTestId('checkout-stepper').getByText('Delivery & configure')).toBeVisible();
    await fillCheckoutDetails(page, { ...guest, email: `mixed-${Date.now()}@example.test` });
    await continueCheckout(page);

    await expect(page.getByRole('radio').first()).toBeVisible();
    await expect(page.getByText('E2E Nova VPS').first()).toBeVisible();
    await page.getByRole('radio').first().check();
    await continueCheckout(page);

    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    await expect(page.locator('.store-summary-line')).toHaveCount(4);
    await placeOrder(page);
    await expect(page).toHaveURL(/\/orders\//);
    await expect(page.getByRole('heading').first()).toBeVisible();
});
