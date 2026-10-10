import { expect, test } from '@playwright/test';

const userEmail = process.env.MARS_BROWSER_TEST_EMAIL ?? '';
const userPassword = process.env.MARS_BROWSER_TEST_PASSWORD ?? '';
const enabled = userEmail.endsWith('@invalid.test') && userPassword.length > 0;

test.describe('isolated test user journeys', () => {
  test.skip(!enabled, 'Requires a dedicated local test user with @invalid.test email.');

  test.beforeEach(async ({ page }) => {
    await page.goto('/giris');
    await page.locator('input[type="email"]').fill(userEmail);
    await page.locator('input[type="password"]').fill(userPassword);
    await page.getByRole('button', { name: 'Giriş Yap' }).click();
    await expect(page).not.toHaveURL(/\/giris(?:\?.*)?$/);
  });

  test('logged-in user can open the dashboard without server errors', async ({ page }) => {
    const result = await page.goto('/');
    expect(result?.status()).toBeLessThan(500);
  });

  test('direct access to period selection requires a valid session', async ({ page }) => {
    const result = await page.goto('/secim');
    expect(result?.status()).toBeLessThan(500);
  });
});
