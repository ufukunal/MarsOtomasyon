import { expect, test } from '@playwright/test';

test('login requires both an email and password before submitting', async ({ page }) => {
  await page.goto('/giris');

  const email = page.locator('input[type="email"]');
  const password = page.locator('input[type="password"]');
  const submit = page.getByRole('button', { name: 'Giriş Yap' });

  await expect(email).toHaveAttribute('required', '');
  await expect(password).toHaveAttribute('required', '');
  await expect(email).toHaveAttribute('autocomplete', 'username');
  await expect(password).toHaveAttribute('autocomplete', 'current-password');

  await submit.click();
  await expect(page).toHaveURL(/\/giris(?:\?.*)?$/);
});

test('login form does not display privileged account details to guests', async ({ page }) => {
  await page.goto('/giris');
  await expect(page.locator('input[type="password"]')).toHaveAttribute('type', 'password');
  await expect(page.locator('body')).not.toContainText('APP_KEY=');
  await expect(page.locator('body')).not.toContainText('DB_PASSWORD=');
  await expect(page.getByRole('link', { name: 'Parolamı unuttum' })).toBeVisible();
});
