import { expect, test } from '@playwright/test';

// The tests never post, delete, approve, print or mutate ERP business data.
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

test.describe('Shared v65 appearance and responsive shell, read-only', () => {
  test.skip(!enabled, 'Requires explicitly approved isolated browser fixtures.');

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

  test('theme preference survives reload and Livewire navigation without changing records', async ({ page }) => {
    await page.getByLabel('Kullanıcı menüsü').click();
    const selector = page.locator('[data-mars-theme-select]');
    await expect(selector).toHaveValue('light');
    await selector.selectOption('dim');
    await expect(page.locator('html')).toHaveAttribute('data-mars-theme', 'dim');

    await selector.selectOption('dark');
    await expect(page.locator('html')).toHaveAttribute('data-mars-theme', 'dark');
    const colors = await page.locator('.app-shell .panel').first().evaluate(element => {
      const computed = getComputedStyle(element);
      return { background: computed.backgroundColor, text: computed.color };
    });
    expect(colors.background).not.toBe(colors.text);

    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-mars-theme', 'dark');
    await page.getByLabel('Kullanıcı menüsü').click();
    await page.locator('[data-mars-theme-select]').selectOption('light');
    await expect(page.locator('html')).toHaveAttribute('data-mars-theme', 'light');
  });

  for (const width of [390, 768, 1024, 1440, 1920]) {
    test('shell navigation stays usable at ' + width + 'px', async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      const errors: string[] = [];
      page.on('pageerror', error => errors.push(error.message));

      await expect(page.locator('main#main-content')).toBeVisible();
      await expect(page.locator('nav.navigation')).toBeVisible();
      const main = await page.locator('main#main-content').boundingBox();
      expect(main).not.toBeNull();
      expect(main!.x + main!.width).toBeLessThanOrEqual(width + 3);

      const skip = page.getByRole('link', { name: 'Ana içeriğe geç' });
      await skip.focus();
      await expect(skip).toBeVisible();
      await skip.press('Enter');
      await expect(page.locator('main#main-content')).toBeFocused();
      expect(errors).toEqual([]);
    });
  }
});
