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
    const priceRow = page.locator('.store-product__price-row');
    await expect(priceRow).toHaveCSS('display', 'flex');
    const priceBox = await priceRow.locator('.store-product__price').boundingBox();
    const quantityBox = await priceRow.locator('.store-qty').boundingBox();
    expect(priceBox).not.toBeNull();
    expect(quantityBox).not.toBeNull();
    expect(quantityBox!.x).toBeLessThan(priceBox!.x);
    expect(Math.abs(priceBox!.y - quantityBox!.y)).toBeLessThanOrEqual(2);

    await page.getByRole('button', { name: 'Increase quantity' }).click();
    await expect(quantity).toHaveValue('2');

    await page.getByRole('button', { name: 'Decrease quantity' }).click();
    await expect(quantity).toHaveValue('1');

    const thumbnails = page.locator('.store-product__thumb');
    await expect(thumbnails).toHaveCount(3);
    const mainImage = page.locator('.store-product__media img');
    await expect(mainImage).toHaveAttribute('src', /gallery-1\.png$/);
    const desktopMediaBox = await page.locator('.store-product__media').boundingBox();
    const layoutBox = await page.locator('.store-product__layout').boundingBox();
    expect(desktopMediaBox).not.toBeNull();
    expect(layoutBox).not.toBeNull();
    expect(desktopMediaBox!.width).toBeLessThanOrEqual(480);
    expect(Math.abs(desktopMediaBox!.x - layoutBox!.x)).toBeLessThanOrEqual(2);

    await thumbnails.nth(1).click();
    await expect(mainImage).toHaveAttribute('src', /gallery-2\.png$/);
    await expect(thumbnails.nth(1)).toHaveAttribute('aria-current', 'true');

    const perksBox = await page.locator('.store-product__perks').boundingBox();
    const actionsBox = await page.locator('.store-product__actions').boundingBox();
    expect(perksBox).not.toBeNull();
    expect(actionsBox).not.toBeNull();
    expect(Math.abs((perksBox!.y + perksBox!.height) - (desktopMediaBox!.y + desktopMediaBox!.height))).toBeLessThanOrEqual(2);
    expect(perksBox!.y - (actionsBox!.y + actionsBox!.height)).toBeGreaterThanOrEqual(40);

    await page.setViewportSize({ width: 768, height: 980 });
    const tabletMediaBox = await page.locator('.store-product__media').boundingBox();
    const tabletLayoutBox = await page.locator('.store-product__layout').boundingBox();
    const tabletPerksBox = await page.locator('.store-product__perks').boundingBox();
    const tabletQuantityBox = await priceRow.locator('.store-qty').boundingBox();
    const tabletPriceBox = await priceRow.locator('.store-product__price').boundingBox();
    expect(tabletMediaBox).not.toBeNull();
    expect(tabletLayoutBox).not.toBeNull();
    expect(tabletPerksBox).not.toBeNull();
    expect(tabletQuantityBox).not.toBeNull();
    expect(tabletPriceBox).not.toBeNull();
    expect(Math.abs(tabletMediaBox!.x - tabletLayoutBox!.x)).toBeLessThanOrEqual(2);
    expect(Math.abs(tabletMediaBox!.width - tabletMediaBox!.height)).toBeLessThanOrEqual(2);
    expect(Math.abs(tabletPerksBox!.y + tabletPerksBox!.height - tabletMediaBox!.y - tabletMediaBox!.height)).toBeLessThanOrEqual(2);
    expect(tabletQuantityBox!.x).toBeLessThan(tabletPriceBox!.x);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    await page.setViewportSize({ width: 390, height: 844 });
    const mobilePriceBox = await priceRow.locator('.store-product__price').boundingBox();
    const mobileQuantityBox = await priceRow.locator('.store-qty').boundingBox();
    expect(mobilePriceBox).not.toBeNull();
    expect(mobileQuantityBox).not.toBeNull();
    expect(mobileQuantityBox!.x).toBeLessThan(mobilePriceBox!.x);
    expect(Math.abs(mobilePriceBox!.y - mobileQuantityBox!.y)).toBeLessThanOrEqual(2);
    const mobileMediaBox = await page.locator('.store-product__media').boundingBox();
    const mobileLayoutBox = await page.locator('.store-product__layout').boundingBox();
    expect(mobileMediaBox).not.toBeNull();
    expect(mobileLayoutBox).not.toBeNull();
    expect(mobileMediaBox!.width).toBeLessThanOrEqual(390);
    expect(Math.abs(mobileMediaBox!.x - mobileLayoutBox!.x)).toBeLessThanOrEqual(2);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(pageErrors).toEqual([]);
});

test('quantity stepper updates locally without a Livewire roundtrip per click', async ({ page }) => {
    const livewireRequests: string[] = [];
    page.on('request', request => {
        if (request.method() !== 'GET' && request.url().includes('/livewire/update')) {
            livewireRequests.push(request.url());
        }
    });

    await page.goto('/products/e2e-physical');
    await chooseEssentialCookies(page);

    const quantity = page.locator('#quantity');
    const increase = page.getByRole('button', { name: 'Increase quantity' });
    await expect(quantity).toHaveValue('1');

    await increase.click();
    await increase.click();
    await increase.click();

    await expect(quantity).toHaveValue('4');
    expect(livewireRequests).toEqual([]);

    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page).toHaveURL(/\/cart$/);
    await expect(page.locator('.store-qty__input')).toHaveValue('4');
});
