import { expect, test } from '@playwright/test';

const protectedPaths = [
  '/kartlar/urunler',
  '/stok/durum',
  '/satis/siparisler',
  '/alis/siparisler',
  '/finans/islemler',
  '/iadeler',
  '/ithalat',
  '/uretim/emirler',
  '/e-ticaret/kanal-hesaplari',
  '/raporlar',
];

test('guest users cannot open the application dashboard', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/giris(?:\?.*)?$/);
  await expect(page.locator('input[type="password"]')).toBeVisible();
});

for (const path of protectedPaths) {
  test('guest access is blocked for ' + path, async ({ page }) => {
    await page.goto(path);
    await expect(page).toHaveURL(/\/giris(?:\?.*)?$/);
    await expect(page.locator('input[type="password"]')).toBeVisible();
  });
}

test('login page offers password recovery but exposes no stack traces', async ({ page }) => {
  await page.goto('/giris');
  await expect(page.locator('input[type="email"]')).toBeVisible();
  await expect(page.locator('input[type="password"]')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Parolamı unuttum' })).toBeVisible();
  await expect(page.getByText('Stack trace', { exact: false })).toHaveCount(0);
});
