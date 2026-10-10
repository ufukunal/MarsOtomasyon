import { expect, test } from '@playwright/test';

const email = process.env.MARS_BROWSER_TEST_EMAIL ?? '';
const password = process.env.MARS_BROWSER_TEST_PASSWORD ?? '';
const companyId = process.env.MARS_BROWSER_TEST_COMPANY_ID ?? '';
const periodId = process.env.MARS_BROWSER_TEST_PERIOD_ID ?? '';
const unitId = process.env.MARS_BROWSER_TEST_PRODUCT_UNIT_ID ?? '';

const approved = process.env.MARS_BROWSER_TEST_MUTATIONS_APPROVED === 'I_APPROVE_LOCAL_UI_WRITES'
  && process.env.MARS_BROWSER_TEST_PERIOD_READY === 'I_APPROVE_LOCAL_PERIOD_FIXTURES'
  && email.endsWith('@invalid.test')
  && password.length > 0
  && /^[1-9]\d*$/.test(companyId)
  && /^[1-9]\d*$/.test(periodId);

test.describe('local disposable browser CRUD workflows', () => {
  test.skip(!approved, 'An isolated local actor, selected period and separate UI write approval are required.');

  test.beforeEach(async ({ page }) => {
    await page.goto('/giris');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: 'Giriş Yap' }).click();
    await expect(page).not.toHaveURL(/\/giris(?:\?.*)?$/);

    await page.goto('/secim');
    const selectors = page.locator('select');
    await selectors.nth(0).selectOption(companyId);
    await selectors.nth(1).selectOption(periodId);
    await page.getByRole('button', { name: 'Devam' }).click();
    await expect(page).toHaveURL(/\/$/);
  });

  test('creates a product card and redirects to the persisted edit screen', async ({ page }) => {
    test.skip(!/^[1-9]\d*$/.test(unitId), 'A disposable local unit fixture ID is required.');

    await page.goto('/kartlar/urunler/yeni');
    const code = 'V4E2E' + Date.now().toString(36).toUpperCase();
    const productName = 'V4 Disposable Product ' + code;

    await page.getByLabel('Kod', { exact: true }).fill(code);
    await page.getByLabel('Ad', { exact: true }).fill(productName);
    await page.locator('select[wire\\:model="unitId"]').selectOption(unitId);
    await page.getByRole('button', { name: 'Ürünü Kaydet' }).click();

    await expect(page).toHaveURL(/\/kartlar\/urunler\/[1-9]\d*$/);
    await expect(page.getByLabel('Ad', { exact: true })).toHaveValue(productName);
    await expect(page.getByLabel('Kod', { exact: true })).toHaveValue(code);
    await expect(page.getByLabel('Kod', { exact: true })).toBeDisabled();
  });

  test('creates a legal contact card and loads it back from the edit route', async ({ page }) => {
    await page.goto('/kartlar/cariler/yeni');
    const name = 'V4 Disposable Customer ' + Date.now().toString(36);

    await page.getByLabel('Unvan', { exact: true }).fill(name);
    await page.getByRole('button', { name: 'Kaydet', exact: true }).click();

    await expect(page).toHaveURL(/\/kartlar\/cariler\/[1-9]\d*$/);
    await expect(page.getByLabel('Unvan', { exact: true })).toHaveValue(name);
  });

  test('rejects empty product and contact cards without creating resources', async ({ page }) => {
    await page.goto('/kartlar/urunler/yeni');
    await page.getByRole('button', { name: 'Ürünü Kaydet' }).click();
    await expect(page).toHaveURL(/\/kartlar\/urunler\/yeni$/);
    await expect(page.locator('.field-error').first()).toBeVisible();

    await page.goto('/kartlar/cariler/yeni');
    await page.getByRole('button', { name: 'Kaydet', exact: true }).click();
    await expect(page).toHaveURL(/\/kartlar\/cariler\/yeni$/);
    await expect(page.locator('.field-error').first()).toBeVisible();
  });
});
