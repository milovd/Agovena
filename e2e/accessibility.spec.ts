import { test, expect } from '@playwright/test';
import { addProductToCart, fillCheckoutDetails } from './helpers';

test('cart and checkout keep keyboard focus and control labels', async ({ page }) => {
    await addProductToCart(page, 'e2e-digital');
    await page.goto('/cart');

    await page.getByRole('button', { name: 'Increase quantity' }).focus();
    await expect(page.getByRole('button', { name: 'Increase quantity' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('.store-qty__input')).toHaveValue('2');

    await page.getByRole('button', { name: /Remove / }).focus();
    await expect(page.getByRole('button', { name: /Remove / })).toBeFocused();

    await page.getByRole('link', { name: 'Checkout' }).focus();
    await expect(page.getByRole('link', { name: 'Checkout' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/checkout/);

    await expect(page.locator('.store-stepper')).toHaveAttribute('aria-label', /progress/i);
    await expect(page.locator('[aria-current="step"]')).toBeVisible();

    await fillCheckoutDetails(page);
    await page.getByRole('button', { name: /^Continue to / }).press('Enter');
    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();

    const method = page.locator('.store-choice--row input[type="radio"]').first();
    await method.focus();
    await expect(method).toBeFocused();
    await page.keyboard.press('Space');
    await expect(method).toBeChecked();

    await page.getByRole('button', { name: /Place order|Pay / }).focus();
    await expect(page.getByRole('button', { name: /Place order|Pay / })).toBeFocused();
});
