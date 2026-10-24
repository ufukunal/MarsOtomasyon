import { expect, test } from '@playwright/test';

// Read-only local UI checks. Never posts or modifies business documents.
const email = process.env.MARS_BROWSER_TEST_EMAIL ?? '';
const password = process.env.MARS_BROWSER_TEST_PASSWORD ?? '';
const companyId = process.env.MARS_BROWSER_TEST_COMPANY_ID ?? '';
const periodId = process.env.MARS_BROWSER_TEST_PERIOD_ID ?? '';
const enabled = email.endsWith('@invalid.test')
  && password.length > 0
  && /^[1-9]\d*$/.test(companyId)
  && /^[1-9]\d*$/.test(periodId)
  && process.env.MARS_BROWSER_TEST_PERIOD_READY === 'I_APPROVE_LOCAL_PERIOD_FIXTURES'
  && process.env.MARS_BROWSER_TEST_DOCUMENT_UI_APPROVED === 'I_APPROVE_LOCAL_DOCUMENT_UI';

test.describe('V65 shell accessibility phase 1 (read only)', () => {
  test.skip(!enabled, 'Requires an explicitly approved isolated browser fixture.');

  test.beforeEach(async ({ page }) => {
    await page.goto('/giris');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: 'Giriş Yap' }).click();
    await expect(page).not.toHaveURL(/\/giris(?:\?.*)?$/);
    await page.goto('/secim');
    const selects = page.locator('select');
    await selects.nth(0).selectOption(companyId);
    await expect(selects.nth(1)).toBeEnabled();
    await selects.nth(1).selectOption(periodId);
    await page.getByRole('button', { name: 'Devam' }).click();
    await expect(page).toHaveURL(/\/$/);
  });

  test('skip navigation is reachable by keyboard and focuses main content', async ({ page }) => {
    const skip = page.getByRole('link', { name: 'Ana içeriğe geç' });
    await expect(skip).toHaveCount(1);
    await skip.focus();
    await expect(skip).toBeFocused();
    await expect(skip).toBeVisible();
    await skip.press('Enter');
    await expect(page.locator('main#main-content')).toBeFocused();
  });

  test('legacy product editor tabs use shared keyboard navigation at mobile width', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 850 });
    await page.goto('/kartlar/urunler/yeni');
    const general = page.getByRole('tab', { name: 'Genel' });
    const price = page.getByRole('tab', { name: 'Fiyat' });
    await expect(general).toHaveAttribute('aria-selected', 'true');
    await expect(general).toHaveAttribute('aria-controls', 'v65-product-edit-panel-general');
    await expect(page.locator('#v65-product-edit-panel-general')).toBeVisible();
    await general.focus();
    await page.keyboard.press('ArrowRight');
    await expect(price).toHaveAttribute('aria-selected', 'true');
    await expect(price).toHaveAttribute('aria-controls', 'v65-product-edit-panel-price');
    await expect(page.locator('#v65-product-edit-panel-price')).toBeVisible();
    await expect(page.locator('main#main-content')).toBeVisible();
    const tabBox = await page.locator('[data-v65-tabs]').boundingBox();
    expect(tabBox).not.toBeNull();
    expect(tabBox!.x + tabBox!.width).toBeLessThanOrEqual(393);
  });

  test('contact tab panels remain connected and keyboard navigable on tablet', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto('/kartlar/cariler/yeni');
    const general = page.getByRole('tab', { name: 'Firma / Ticari' });
    const terms = page.getByRole('tab', { name: 'Ticari Koşullar' });
    await expect(general).toHaveAttribute('aria-controls', 'v65-contact-edit-panel-general');
    await expect(page.locator('#v65-contact-edit-panel-general')).toBeVisible();

    await general.focus();
    await general.press('ArrowRight');
    await expect(terms).toHaveAttribute('aria-selected', 'true');
    await expect(terms).toHaveAttribute('aria-controls', 'v65-contact-edit-panel-terms');
    await expect(page.locator('#v65-contact-edit-panel-terms')).toBeVisible();
  });

});
