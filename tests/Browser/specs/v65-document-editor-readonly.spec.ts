import { expect, test } from '@playwright/test';

// Read-only UI verification. These routes open empty editors and never submit,
// confirm, post, approve, reserve, reverse or modify any business record.
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

const editors = [
  { name: 'Satış Teklifi', url: '/satis/teklif' },
  { name: 'Satış Siparişi', url: '/satis/siparis' },
  { name: 'Satış İrsaliyesi', url: '/satis/irsaliye' },
  { name: 'Satış Faturası', url: '/satis/fatura' },
  { name: 'Alış Siparişi', url: '/alis/siparis' },
  { name: 'Alış Faturası', url: '/alis/fatura' },
];

const sizes = [390, 768, 1024, 1440, 1920];

test.describe('V65 document editors: isolated read-only browser inspection', () => {
  test.skip(!enabled, 'Requires separately approved local actor, period and document UI scope.');

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

  for (const editor of editors) {
    for (const width of sizes) {
      test(`${editor.name} – ${width}px safe rendering`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors: string[] = [];
        page.on('pageerror', error => errors.push(error.message));

        const response = await page.goto(editor.url);
        expect(response?.status()).toBe(200);

        const form = page.locator('.document-editor-form.v65-doc-editor');
        await expect(form).toBeVisible();
        await expect(page.locator('.v65-doc-heading')).toBeVisible();
        await expect(page.locator('.v65-doc-summary')).toBeVisible();

        // Intentionally scroll the line grid rather than overflowing the page.
        const table = page.locator('.v65-doc-table-scroll').first();
        await expect(table).toBeVisible();
        const bounds = await table.boundingBox();
        expect(bounds).not.toBeNull();
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width + 3);
        expect(errors).toEqual([]);
      });
    }
  }

  test('Mal Kabul: read-only viewing requires a specifically provisioned isolated receipt', async ({ page }) => {
    const receiptId = process.env.MARS_BROWSER_GOODS_RECEIPT_ID ?? '';
    test.skip(!/^[1-9]\d*$/.test(receiptId), 'No isolated test GoodsReceipt id provisioned.');

    const response = await page.goto('/alis/mal-kabul/' + receiptId);
    expect(response?.status()).toBe(200);
    await expect(page.locator('.document-workspace')).toBeVisible();
    await expect(page.locator('.v65-doc-heading')).toBeVisible();
    await expect(page.locator('.v65-doc-summary')).toBeVisible();
  });
});
