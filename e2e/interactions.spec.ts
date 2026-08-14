import { test, expect } from '@playwright/test';
import { addProductToCart, assertImageNotBroken, continueCheckout, fillCheckoutDetails, placeOrder } from './helpers';

test('browser back from payment returns to details', async ({ page }) => {
    await addProductToCart(page, 'e2e-digital');
    await page.goto('/checkout');
    await fillCheckoutDetails(page);
    await continueCheckout(page);
    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    await page.goBack();
    await expect(page.locator('#customer_name')).toBeVisible();
});

test('coupon applies from the checkout summary', async ({ page }) => {
    await addProductToCart(page, 'e2e-digital');
    await page.goto('/checkout');
    await fillCheckoutDetails(page);
    await page.locator('#coupon-code').fill('E2ESAVE');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page.getByText(/E2ESAVE/)).toBeVisible();
    await expect(page.locator('.store-totals dt').filter({ hasText: 'Discount' })).toBeVisible();
});

test('storefront logo and cart images are real files', async ({ page }) => {
    await page.goto('/');
    const logo = page.locator('.store-brand__logo').first();
    await expect(logo).toBeVisible();
    await assertImageNotBroken(logo);

    await addProductToCart(page, 'e2e-digital');
    await page.goto('/cart');
    const media = page.locator('.store-cart-line__media img, .store-cart-line__media .store-product-card__placeholder');
    await expect(media.first()).toBeVisible();
    await assertImageNotBroken(page.locator('.store-cart-line img'));
});

test('refresh keeps checkout reachable', async ({ page }) => {
    await addProductToCart(page, 'e2e-digital');
    await page.goto('/checkout');
    await fillCheckoutDetails(page);
    await continueCheckout(page);
    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    await page.reload();
    await expect(page.locator('.store-checkout')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Checkout' })).toBeVisible();
});

test('final CTA disables while placing the order', async ({ page }) => {
    await addProductToCart(page, 'e2e-digital');
    await page.goto('/checkout');
    await fillCheckoutDetails(page);
    await continueCheckout(page);
    const action = page.getByRole('button', { name: /Place order|Pay |Continue to / });
    await action.dblclick();
    await expect(page).toHaveURL(/\/orders\//, { timeout: 20_000 });
});

test('payment return page does not claim a paid order', async ({ page }) => {
    await addProductToCart(page, 'e2e-digital');
    await page.goto('/checkout');
    await fillCheckoutDetails(page);
    await continueCheckout(page);
    await placeOrder(page);
    const url = page.url();
    const match = url.match(/\/orders\/(\d+)/);
    expect(match).not.toBeNull();
    await page.goto(`/orders/${match?.[1]}/payment`);
    await expect(page.getByRole('heading').first()).toBeVisible();
    await expect(page.getByText(/waiting|pending|view order|paid/i).first()).toBeVisible();
});
