// Playwright dependency is deliberately not bundled. Run only after written approval
// against a clean STAGING environment, with a precreated non-production storage state.
import { test, expect } from '@playwright/test';

const base = process.env.TEST_V2_BROWSER_URL;
const host = process.env.TEST_V2_BROWSER_ALLOW_HOST;
if (!base || !host || process.env.TEST_V2_APPROVAL !== 'RUN_TEST_V2') {
  throw new Error('Browser execution is disabled: explicit approval and staging host required');
}
const parsed = new URL(base);
if (parsed.protocol !== 'https:' || parsed.hostname !== host) {
  throw new Error('Staging origin does not equal approved hostname');
}

const pages = [
  ['/satis/siparisler', 'Sales orders'],
  ['/alis/siparisler', 'Purchase orders'],
  ['/iadeler', 'Returns'],
  ['/finans/hesaplar', 'Finance accounts'],
  ['/ithalat', 'Import'],
  ['/uretim/emirler', 'Production'],
  ['/e-ticaret/kanal-hesaplari', 'Marketplace accounts'],
];

for (const [path, label] of pages) {
  test(`v2 E2E protected navigation: ${label}`, async ({ page }) => {
    const response = await page.goto(new URL(path, base).toString());
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(500);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });
}

test('v2 E2E anonymous protected route redirects to login', async ({ browser }) => {
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  const page = await context.newPage();
  await page.goto(new URL('/satis/siparisler', base).toString());
  await expect(page).toHaveURL(/\/giris(?:\?.*)?$/);
  await context.close();
});