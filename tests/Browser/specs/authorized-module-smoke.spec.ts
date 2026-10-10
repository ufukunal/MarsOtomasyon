import { expect, test } from '@playwright/test';

const email = process.env.MARS_BROWSER_TEST_EMAIL ?? '';
const password = process.env.MARS_BROWSER_TEST_PASSWORD ?? '';
const companyId = process.env.MARS_BROWSER_TEST_COMPANY_ID ?? '';
const periodId = process.env.MARS_BROWSER_TEST_PERIOD_ID ?? '';

const ready = email.endsWith('@invalid.test')
  && password.length > 0
  && /^[1-9]\d*$/.test(companyId)
  && /^[1-9]\d*$/.test(periodId)
  && process.env.MARS_BROWSER_TEST_PERIOD_READY === 'I_APPROVE_LOCAL_PERIOD_FIXTURES';

test.describe('authenticated read-only module journeys in an isolated local database', () => {
  test.skip(!ready, 'Local actor, company and period fixture IDs are required.');

  test.beforeEach(async ({ page }) => {
    await page.goto('/giris');
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.getByRole('button', { name: 'Giriş Yap' }).click();
    await expect(page).not.toHaveURL(/\/giris(?:\?.*)?$/);

    await page.goto('/secim');
    const selectors = page.locator('select');
    await selectors.nth(0).selectOption(companyId);
    await expect(selectors.nth(1)).toBeEnabled();
    await selectors.nth(1).selectOption(periodId);
    await page.getByRole('button', { name: 'Devam' }).click();

    await expect(page).toHaveURL(/\/$/);
  });

  const screens = [
    { area: 'catalog', url: '/kartlar/urunler' },
    { area: 'stock', url: '/stok/durum' },
    { area: 'sales', url: '/satis/siparisler' },
    { area: 'purchases', url: '/alis/siparisler' },
    { area: 'finance', url: '/finans/islemler' },
    { area: 'returns', url: '/iadeler' },
    { area: 'imports', url: '/ithalat' },
    { area: 'production', url: '/uretim/emirler' },
    { area: 'channels', url: '/e-ticaret/kanal-hesaplari' },
    { area: 'reports', url: '/raporlar' },
  ];

  for (const screen of screens) {
    test('renders the ' + screen.area + ' screen for an authorized period actor', async ({ page }) => {
      const response = await page.goto(screen.url);
      expect(response?.status()).toBe(200);
      await expect(page).not.toHaveURL(/\/giris(?:\?.*)?$/);
      await expect(page.locator('body')).toBeVisible();
    });
  }
});
