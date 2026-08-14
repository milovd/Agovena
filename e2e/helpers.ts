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
    await expect(page.getByLabel('Name', { exact: true })).toBeVisible();
    await page.getByLabel('Name', { exact: true }).fill(identity.name);
    await page.getByLabel('Name', { exact: true }).blur();
    await page.getByLabel('Email', { exact: true }).fill(identity.email);
    await page.getByLabel('Email', { exact: true }).blur();
    await page.getByLabel('Full name').fill(identity.name);
    await page.getByLabel('Full name').blur();
    await page.getByLabel('Address line 1').fill(identity.line1);
    await page.getByLabel('Address line 1').blur();
    await page.getByLabel('Postal code').fill(identity.postal);
    await page.getByLabel('Postal code').blur();
    await page.getByLabel('City').fill(identity.city);
    await page.getByLabel('City').blur();
    if (await page.getByLabel('Country').count()) {
        await page.getByLabel('Country').selectOption('NL');
    }
    await page.waitForTimeout(400);
}

export async function continueCheckout(page: Page): Promise<void> {
    const current = page.locator('[data-testid="checkout-stepper"] [aria-current="step"]');
    const currentLabel = ((await current.textContent()) ?? '').trim();
    await page.getByTestId('checkout-continue').click();
    await expect(page.locator('[data-testid="checkout-stepper"] [aria-current="step"]')).not.toHaveText(currentLabel, { timeout: 15_000 });
}

export async function placeOrder(page: Page): Promise<void> {
    const payLater = page.getByRole('radio', { name: 'Pay later' });
    if (await payLater.count()) {
        await payLater.check();
        await page.waitForTimeout(300);
    }

    const action = page.getByTestId('checkout-submit');
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
        const src = (await img.getAttribute('src')) ?? '';
        if (src === '') {
            continue;
        }

        const inViewport = await img.evaluate((el) => {
            const box = el.getBoundingClientRect();
            if (box.width < 8 || box.height < 8) {
                return false;
            }

            const sampleX = Math.min(window.innerWidth - 1, Math.max(0, box.left + box.width / 2));
            const sampleY = Math.min(window.innerHeight - 1, Math.max(0, box.top + box.height / 2));
            const hit = document.elementFromPoint(sampleX, sampleY);

            return hit === el || (hit !== null && el.contains(hit)) || (hit !== null && hit.contains(el));
        });
        if (! inViewport) {
            continue;
        }

        await expect.poll(async () => img.evaluate((el) => {
            const image = el as HTMLImageElement;

            return image.complete && image.naturalWidth > 0;
        }), { timeout: 8_000 }).toBeTruthy();
    }
}
