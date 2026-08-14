import { expect, type Page } from '@playwright/test';

export const guest = {
    name: 'Ada Guest',
    email: `ada-${Date.now()}@example.test`,
    line1: 'Keizersgracht 1',
    postal: '1015 CJ',
    city: 'Amsterdam',
};

export async function addProductToCart(page: Page, slug: string, options: Record<string, string> = {}): Promise<void> {
    await page.goto(`/products/${slug}`);
    await expect(page.getByRole('button', { name: 'Add to cart' })).toBeEnabled();

    for (const value of Object.values(options)) {
        const select = page.locator('.store-product__options select').first();
        if (await select.count()) {
            await select.selectOption(value);
        }
    }

    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to cart.')).toBeVisible({ timeout: 15_000 });
}

export async function fillCheckoutDetails(page: Page, identity = guest): Promise<void> {
    await expect(page.locator('#customer_name')).toBeVisible();
    await page.locator('#customer_name').fill(identity.name);
    await page.locator('#customer_name').blur();
    await page.locator('#customer_email').fill(identity.email);
    await page.locator('#customer_email').blur();
    await page.locator('#billing_name').fill(identity.name);
    await page.locator('#billing_name').blur();
    await page.locator('#billing_line1').fill(identity.line1);
    await page.locator('#billing_line1').blur();
    await page.locator('#billing_postal_code').fill(identity.postal);
    await page.locator('#billing_postal_code').blur();
    await page.locator('#billing_city').fill(identity.city);
    await page.locator('#billing_city').blur();
    if (await page.locator('#billing_country').count()) {
        await page.locator('#billing_country').selectOption('NL');
    }
    await page.waitForTimeout(400);
}

export async function continueCheckout(page: Page): Promise<void> {
    const completedBefore = await page.locator('.store-stepper__item--completed').count();
    await page.getByRole('button', { name: /^Continue to / }).click();
    await expect(page.locator('.store-stepper__item--completed')).toHaveCount(completedBefore + 1, { timeout: 15_000 });
}

export async function placeOrder(page: Page): Promise<void> {
    const payLater = page.locator('.store-choice').filter({ hasText: 'Pay later' }).locator('input[type="radio"]');
    if (await payLater.count()) {
        await payLater.check();
        await page.waitForTimeout(300);
    }

    const action = page.getByRole('button', { name: /Place order|Pay |Continue to Mollie|Continue to Stripe/ });
    await expect(action).toBeVisible();
    await action.click();
    await expect(page).toHaveURL(/\/orders\//, { timeout: 20_000 });
}

export async function assertNoHorizontalScroll(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
}

export async function assertImageNotBroken(locator: import('@playwright/test').Locator): Promise<void> {
    const count = await locator.count();
    for (let i = 0; i < count; i++) {
        const img = locator.nth(i);
        if (!(await img.isVisible())) {
            continue;
        }
        const ok = await img.evaluate((el) => {
            const image = el as HTMLImageElement;

            return image.complete && image.naturalWidth > 0;
        });
        expect(ok, `broken image: ${await img.getAttribute('src')}`).toBeTruthy();
    }
}
