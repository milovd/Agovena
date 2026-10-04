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

test('menu stays at the right of the search and cart while resizing', async ({ page }) => {
    await page.goto('/');
    await chooseEssentialCookies(page);

    for (const width of [767, 768, 900, 1099]) {
        await page.setViewportSize({ width, height: 900 });
        const menu = await page.locator('.store-header__menu').boundingBox();
        const cart = await page.locator('.store-header__cart').boundingBox();
        const search = await page.locator(width < 768 ? '.store-header__mobile-search' : '.store-header__search-wrap:not(.store-header__search-wrap--mobile)').boundingBox();
        expect(menu && cart && search).toBeTruthy();
        expect(menu!.x).toBeGreaterThan(cart!.x);
        expect(menu!.x - (search!.x + search!.width)).toBeGreaterThanOrEqual(8);
        expect(menu!.x + menu!.width).toBeLessThanOrEqual(width);
    }
});

test('desktop navigation keeps its links clear of the search field', async ({ page }) => {
    await page.goto('/');
    await chooseEssentialCookies(page);

    for (const width of [1440, 1200, 1160, 1100]) {
        await page.setViewportSize({ width, height: 900 });
        const links = page.locator('.store-nav .store-nav__link');
        const search = await page.locator('.store-header__search-wrap:not(.store-header__search-wrap--mobile)').boundingBox();
        const lastLink = await links.last().boundingBox();
        const actions = await page.locator('.store-header__actions').boundingBox();
        expect(await links.count()).toBeGreaterThanOrEqual(3);
        expect(search && lastLink && actions).toBeTruthy();
        expect(search!.x - (lastLink!.x + lastLink!.width)).toBeGreaterThanOrEqual(8);
        expect(actions!.x - (search!.x + search!.width)).toBeGreaterThanOrEqual(8);
    }
});

test('extra navigation items move to the existing drawer when space runs out', async ({ page }) => {
    await page.goto('/');
    await chooseEssentialCookies(page);
    await page.evaluate(() => {
        const link = document.createElement('a');
        link.href = '/products';
        link.textContent = 'More offers';
        link.className = 'store-nav__link';
        document.querySelector('.store-nav')!.append(link);
        const drawerLink = link.cloneNode(true) as HTMLAnchorElement;
        drawerLink.classList.add('store-drawer__primary-link');
        document.querySelector('.store-drawer__nav')!.append(drawerLink);
    });

    await page.setViewportSize({ width: 1100, height: 900 });
    const menu = page.locator('.store-header__menu');
    await expect(menu).toBeVisible();
    await expect(page.locator('.store-nav')).toBeHidden();
    const cart = await page.locator('.store-header__cart').boundingBox();
    const menuBox = await menu.boundingBox();
    expect(menuBox && cart).toBeTruthy();
    expect(menuBox!.x).toBeGreaterThan(cart!.x);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);

    await menu.click();
    await expect(page.locator('#store-mobile-nav')).toBeVisible();
    await expect(page.locator('.store-drawer__nav').getByText('More offers')).toBeVisible();

    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(page.locator('.store-nav')).toBeVisible();
    await expect(menu).toBeHidden();
    await expect(page.locator('#store-mobile-nav')).toBeHidden();
    await expect(page.locator('body')).not.toHaveClass(/store-drawer-open/);

    await page.evaluate(() => {
        for (let index = 0; index < 5; index++) {
            const link = document.createElement('a');
            link.href = '/products';
            link.textContent = `Additional item ${index}`;
            link.className = 'store-nav__link';
            document.querySelector('.store-nav')!.append(link);
            const drawerLink = link.cloneNode(true) as HTMLAnchorElement;
            drawerLink.classList.add('store-drawer__primary-link');
            document.querySelector('.store-drawer__nav')!.append(drawerLink);
        }
    });
    await page.setViewportSize({ width: 1300, height: 900 });
    await expect(menu).toBeVisible();
    await expect(page.locator('.store-nav')).toBeHidden();
    await menu.click();
    await expect(page.locator('.store-drawer__nav').getByText('Additional item 4')).toBeVisible();
});

test('product quantity and gallery remain interactive', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', error => pageErrors.push(error.message));

    await page.goto('/products/e2e-physical');
    await chooseEssentialCookies(page);

    const quantity = page.locator('#quantity');
    await expect(quantity).toHaveValue('1');
    const priceRow = page.locator('.store-product__price-row');
    const quantityRow = page.locator('.store-product__quantity-row');
    await expect(quantityRow.locator('label[for="quantity"]')).toHaveClass(/visually-hidden/);
    await expect(quantityRow.locator('.store-qty__btn').first()).toHaveCSS('height', '40px');
    const mainImage = page.locator('.store-product__media img');
    await expect(mainImage).toHaveAttribute('fetchpriority', 'high');
    await expect(mainImage).toHaveAttribute('loading', 'eager');
    await expect(mainImage).toHaveAttribute('src', /gallery-1\.png$/);

    const checkProductSpacing = async (maxGap: number) => {
        const media = await page.locator('.store-product__media').boundingBox();
        const info = await page.locator('.store-product__info').boundingBox();
        const title = await page.locator('.store-product__title').boundingBox();
        const rating = await page.locator('.store-product__rating').boundingBox();
        const lede = await page.locator('.store-product__lede').boundingBox();
        const price = await priceRow.locator('.store-product__price').boundingBox();
        const quantityControl = await quantityRow.boundingBox();
        const actions = await page.locator('.store-product__actions').boundingBox();
        expect(media && info && title && rating && lede && price && quantityControl && actions).toBeTruthy();
        expect(rating!.y - (title!.y + title!.height)).toBeGreaterThanOrEqual(0);
        expect(rating!.y - (title!.y + title!.height)).toBeLessThanOrEqual(12);
        expect(price!.y - (lede!.y + lede!.height)).toBeGreaterThanOrEqual(32);
        expect(quantityControl!.y - (price!.y + price!.height)).toBeGreaterThanOrEqual(0);
        expect(actions!.y - (quantityControl!.y + quantityControl!.height)).toBeGreaterThanOrEqual(20);
        if (media!.x < info!.x) {
            expect(info!.x - (media!.x + media!.width)).toBeLessThanOrEqual(maxGap);
            expect(Math.abs(title!.y - media!.y)).toBeLessThanOrEqual(2);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    };
    await checkProductSpacing(32);

    await page.getByRole('button', { name: 'Increase quantity' }).click();
    await expect(quantity).toHaveValue('2');
    await page.getByRole('button', { name: 'Decrease quantity' }).click();
    await expect(quantity).toHaveValue('1');

    const thumbnails = page.locator('.store-product__thumb');
    await expect(thumbnails).toHaveCount(3);
    await thumbnails.nth(1).click();
    await expect(mainImage).toHaveAttribute('src', /gallery-2\.png$/);
    await expect(thumbnails.nth(1)).toHaveAttribute('aria-current', 'true');

    const perks = await page.locator('.store-product__perks').boundingBox();
    const actions = await page.locator('.store-product__actions').boundingBox();
    expect(perks && actions).toBeTruthy();
    expect(perks!.y - (actions!.y + actions!.height)).toBeGreaterThanOrEqual(30);
    expect(perks!.y - (actions!.y + actions!.height)).toBeLessThanOrEqual(48);

    await page.setViewportSize({ width: 768, height: 980 });
    await checkProductSpacing(32);
    await page.setViewportSize({ width: 390, height: 844 });
    await checkProductSpacing(32);
    expect(pageErrors).toEqual([]);
});

test('product specifications remain readable at 320px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 740 });
    await page.goto('/products/e2e-physical');
    await chooseEssentialCookies(page);

    const table = page.locator('.store-product-specs__table').first();
    const value = table.locator('dd').first();
    await expect(value).toBeVisible();
    const tableBox = await table.boundingBox();
    const valueBox = await value.boundingBox();
    expect(tableBox && valueBox).toBeTruthy();
    expect(valueBox!.width).toBeGreaterThan(48);
    expect(valueBox!.x + valueBox!.width).toBeLessThanOrEqual(tableBox!.x + tableBox!.width);
    expect(await table.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
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

test('product configuration options render responsively and continue to checkout', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/products/e2e-vps/configure?intent=checkout');
    await chooseEssentialCookies(page);

    const continueButton = page.locator('.store-product__form button[type="submit"]');
    await expect(continueButton.locator('span').first()).toHaveText('Continue');

    const operatingSystem = page.locator('.store-field').filter({ hasText: 'Operating system' }).locator('select');
    const notes = page.locator('.store-field').filter({ hasText: 'Setup notes' }).locator('textarea');
    await expect(operatingSystem).toBeVisible();
    await expect(notes).toBeVisible();
    await operatingSystem.selectOption('ubuntu');
    await notes.fill('Keep backups enabled');

    const desktopForm = await page.locator('.store-product__form').boundingBox();
    const desktopNotes = await notes.boundingBox();
    expect(desktopForm).not.toBeNull();
    expect(desktopNotes).not.toBeNull();
    expect(desktopNotes!.width).toBeLessThan(desktopForm!.width);
    await page.screenshot({ path: 'test-results/product-configure-options-desktop.png', fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    const mobileNotes = await notes.boundingBox();
    expect(mobileNotes).not.toBeNull();
    expect(mobileNotes!.width).toBeLessThanOrEqual(390);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: 'test-results/product-configure-options-mobile.png', fullPage: true });

    await continueButton.click();
    await expect(page).toHaveURL(/\/checkout$/);
    expect(pageErrors).toEqual([]);
});
