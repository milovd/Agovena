import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const outDir = path.resolve('docs/browser-review');
fs.mkdirSync(outDir, { recursive: true });

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

async function shot(page, name, fullPage = true) {
    await page.waitForTimeout(300);
    await page.screenshot({ path: path.join(outDir, `${name}.png`), fullPage });
    console.log(name);
}

async function addAndCheckout(page, slug, options = {}) {
    await page.goto(`${baseURL}/products/${slug}`);
    for (const value of Object.values(options)) {
        const select = page.locator('.store-product__options select').first();
        if (await select.count()) {
            await select.selectOption(value);
        }
    }
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await page.getByText('Added to cart.').waitFor();
    await page.goto(`${baseURL}/checkout`);
    await page.locator('#customer_name').waitFor();
}

async function fillDetails(page) {
    await page.locator('#customer_name').fill('Ada Guest');
    await page.locator('#customer_name').blur();
    await page.locator('#customer_email').fill(`ada-review-${Date.now()}@example.test`);
    await page.locator('#customer_email').blur();
    await page.locator('#billing_name').fill('Ada Guest');
    await page.locator('#billing_name').blur();
    await page.locator('#billing_line1').fill('Keizersgracht 1');
    await page.locator('#billing_line1').blur();
    await page.locator('#billing_postal_code').fill('1015 CJ');
    await page.locator('#billing_postal_code').blur();
    await page.locator('#billing_city').fill('Amsterdam');
    await page.locator('#billing_city').blur();
    await page.waitForTimeout(400);
}

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
page.setDefaultTimeout(20_000);

await page.goto(baseURL + '/');
await shot(page, 'desktop-home');

const product = page.locator('.store-product-card__link').filter({ hasNotText: /^E2E / }).first();
await (await product.count() ? product : page.locator('.store-product-card__link').first()).click();
await page.getByRole('button', { name: 'Add to cart' }).waitFor();
await shot(page, 'desktop-pdp');
await page.getByRole('button', { name: 'Add to cart' }).click();
await page.getByText('Added to cart.').waitFor();
await page.goto(baseURL + '/cart');
await shot(page, 'desktop-cart');

await page.getByRole('link', { name: 'Checkout', exact: true }).click();
await page.locator('#customer_name').waitFor();
await shot(page, 'desktop-checkout-details');
await shot(page, 'desktop-checkout-header', false);

await fillDetails(page);
await page.getByRole('button', { name: /^Continue to / }).click();
await page.waitForTimeout(700);
await shot(page, 'desktop-checkout-payment');

const physical = await context.newPage();
physical.setDefaultTimeout(20_000);
await addAndCheckout(physical, 'e2e-physical');
await fillDetails(physical);
await physical.getByRole('button', { name: /^Continue to / }).click();
await physical.getByRole('heading', { name: 'Shipping address' }).waitFor();
await shot(physical, 'desktop-checkout-delivery');
await physical.close();

const configure = await context.newPage();
configure.setDefaultTimeout(20_000);
await addAndCheckout(configure, 'e2e-vps', { os: 'ubuntu' });
await fillDetails(configure);
await configure.getByRole('button', { name: /^Continue to / }).click();
await configure.getByRole('heading', { name: /Configure|Configuration/ }).waitFor();
await shot(configure, 'desktop-checkout-configure');
await configure.close();

await page.setViewportSize({ width: 390, height: 844 });
await page.goto(baseURL + '/cart');
await shot(page, 'mobile-cart');
await page.goto(baseURL + '/checkout');
await shot(page, 'mobile-checkout');

await browser.close();
console.log(`Wrote review screenshots to ${outDir}`);
