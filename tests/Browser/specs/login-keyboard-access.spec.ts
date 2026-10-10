import { expect, test } from '@playwright/test';

test('login fields have accessible labels and follow keyboard tab order', async ({ page }) => {
  await page.goto('/giris');

  const email = page.getByLabel('E-posta');
  const password = page.getByLabel('Parola');
  const remember = page.getByRole('checkbox', { name: 'Beni hatırla' });

  await expect(email).toBeVisible();
  await expect(password).toBeVisible();
  await expect(remember).not.toBeChecked();

  await email.focus();
  await email.press('Tab');
  await expect(password).toBeFocused();

  await password.press('Tab');
  await expect(remember).toBeFocused();
  await remember.press('Space');
  await expect(remember).toBeChecked();
});

test('forgotten-password link is reachable from the login screen without authentication', async ({ page }) => {
  await page.goto('/giris');

  const recovery = page.getByRole('link', { name: 'Parolamı unuttum' });
  await expect(recovery).toHaveAttribute('href', /.+/);
  await recovery.click();

  await expect(page).not.toHaveURL(/\/giris(?:\?.*)?$/);
  await expect(page.locator('body')).toBeVisible();
});
