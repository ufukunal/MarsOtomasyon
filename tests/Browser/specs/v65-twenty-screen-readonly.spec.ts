import { expect, test } from '@playwright/test';

// Source screen list: docs/quality/2026-10-17-20-screen-approved-html-implementation.md.
// All cases are GET-only and require an isolated test company / 20-screen-authorized actor.
const email = process.env.MARS_BROWSER_TEST_EMAIL ?? '';
const password = process.env.MARS_BROWSER_TEST_PASSWORD ?? '';
const companyId = process.env.MARS_BROWSER_TEST_COMPANY_ID ?? '';
const periodId = process.env.MARS_BROWSER_TEST_PERIOD_ID ?? '';
const allowed = email.endsWith('@invalid.test')
  && password.length > 0
  && /^[1-9]\d*$/.test(companyId)
  && /^[1-9]\d*$/.test(periodId)
  && process.env.MARS_BROWSER_TEST_PERIOD_READY === 'I_APPROVE_LOCAL_PERIOD_FIXTURES'
  && process.env.MARS_BROWSER_TEST_20_SCREEN_APPROVED === 'I_APPROVE_LOCAL_20_SCREEN_READONLY';

const sourceId = process.env.MARS_BROWSER_PURCHASE_DISPATCH_ID ?? '';
const receiptId = process.env.MARS_BROWSER_GOODS_RECEIPT_ID ?? '';
const screens = [
  { key: '01 Satış Teklifi', url: '/satis/teklif' },
  { key: '02 Satış Siparişi', url: '/satis/siparis' },
  { key: '03 Satış İrsaliyesi', url: '/satis/irsaliye' },
  { key: '04 Satış Faturası', url: '/satis/fatura' },
  { key: '05 Alış Teklifi', url: '/alis/teklif' },
  { key: '06 Satınalma Siparişi', url: '/alis/siparis' },
  { key: '07 Alış İrsaliyesi', url: /^[1-9]\d*$/.test(sourceId) ? '/alis/irsaliye/' + sourceId : '' },
  { key: '08 Mal Kabul', url: /^[1-9]\d*$/.test(receiptId) ? '/alis/mal-kabul/' + receiptId : '' },
  { key: '09 Alış Faturası', url: '/alis/fatura' },
  { key: '10 Tahsilat', url: '/finans/tahsilat' },
  { key: '11 Tediye', url: '/alis/odeme' },
  { key: '12 Cari Virman', url: '/finans/cari-virman' },
  { key: '13 Çek Listesi', url: '/finans/cekler' },
  { key: '14 Alınan Çek', url: '/finans/alinan-cek' },
  { key: '15 Verilen Çek', url: '/finans/verilen-cek' },
  { key: '16 Çek Ciro', url: '/finans/cek-ciro' },
  { key: '17 Senet Listesi', url: '/finans/senetler' },
  { key: '18 Alınan Senet', url: '/finans/alinan-senet' },
  { key: '19 Verilen Senet', url: '/finans/verilen-senet' },
  { key: '20 Senet Ciro', url: '/finans/senet-ciro' },
];

test.describe('20 approved UI screen routes: isolated, read-only responsive checks', () => {
  test.skip(!allowed, 'Requires separate 20-screen read-only execution approval and isolated fixtures.');

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

  for (const screen of screens) {
    for (const width of [390, 1440]) {
      test(screen.key + ' - ' + width + 'px', async ({ page }) => {
        test.skip(screen.url === '', 'Needs existing authorized test-only source document ID.');
        await page.setViewportSize({ width, height: 900 });

        const errors: string[] = [];
        page.on('pageerror', error => errors.push(error.message));
        const response = await page.goto(screen.url);
        expect(response?.status()).toBe(200);

        const main = page.locator('main#main-content');
        await expect(main).toBeVisible();
        await expect(page.locator('.page-header')).toBeVisible();

        const bounds = await main.boundingBox();
        expect(bounds).not.toBeNull();
        expect(bounds!.x).toBeGreaterThanOrEqual(-3);
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width + 3);
        expect(errors).toEqual([]);
      });
    }
  }
});
