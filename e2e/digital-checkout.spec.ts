import { test, expect } from '@playwright/test';
import { addProductToCart, continueCheckout, fillCheckoutDetails, placeOrder, assertImageNotBroken } from './helpers';

test('digital checkout is details then payment then complete', async ({ page }) => {
    await addProductToCart(page, 'e2e-digital');
    await page.goto('/checkout');

    await expect(page.getByTestId('checkout-stepper')).toBeVisible();
    await expect(page.getByText('Details', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Payment', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Delivery', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Review', { exact: true })).toHaveCount(0);

    await fillCheckoutDetails(page);
    await continueCheckout(page);

    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    await expect(page.getByRole('radio').first()).toBeVisible();
    await expect(page.locator('input[name*="card"], input[autocomplete="cc-number"]')).toHaveCount(0);
    await assertImageNotBroken(page.locator('.store-summary-line img, .store-brand__logo'));

    await expect(page.getByTestId('checkout-submit')).toBeVisible();
    await expect(page.getByTestId('checkout-payment-methods').getByRole('radio').first()).toBeVisible();
    await placeOrder(page);

    await expect(page.getByRole('heading').first()).toBeVisible();
    await expect(page).toHaveURL(/\/orders\//);
});
