import { test, expect } from '@playwright/test';
import { chooseEssentialCookies } from './helpers';

test('category menu closes after leaving the hover region', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', error => pageErrors.push(error.message));

    await page.goto('/');
    await chooseEssentialCookies(page);

    const categories = page.locator('.store-cats');
    const panel = page.locator('#store-cats-panel');
    await expect(categories).toBeVisible();

    await categories.hover();
    await expect(panel).toBeVisible();
    await expect(categories.locator(':scope > .store-nav__link')).toHaveAttribute('aria-expanded', 'true');

    await page.mouse.move(10, 850);
    await expect(panel).toBeHidden();
    await expect(categories.locator(':scope > .store-nav__link')).toHaveAttribute('aria-expanded', 'false');
    expect(pageErrors).toEqual([]);
});

test('product quantity and gallery remain interactive', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', error => pageErrors.push(error.message));

    await page.goto('/products/e2e-physical');
    await chooseEssentialCookies(page);

    const quantity = page.locator('#quantity');
    await expect(quantity).toHaveValue('1');

    await page.getByRole('button', { name: 'Increase quantity' }).click();
    await expect(quantity).toHaveValue('2');

    await page.getByRole('button', { name: 'Decrease quantity' }).click();
    await expect(quantity).toHaveValue('1');

    const thumbnails = page.locator('.store-product__thumb');
    await expect(thumbnails).toHaveCount(3);
    const mainImage = page.locator('.store-product__media img');
    await expect(mainImage).toHaveAttribute('src', /gallery-1\.png$/);

    await thumbnails.nth(1).click();
    await expect(mainImage).toHaveAttribute('src', /gallery-2\.png$/);
    await expect(thumbnails.nth(1)).toHaveAttribute('aria-current', 'true');
    expect(pageErrors).toEqual([]);
});
