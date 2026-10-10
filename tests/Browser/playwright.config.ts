import { defineConfig, devices } from '@playwright/test';

const approved = process.env.MARS_TEST_EXECUTION_APPROVED === 'MARS_V4_EXECUTION_APPROVED'
  && process.env.MARS_BROWSER_TESTS_APPROVED === 'I_APPROVE_LOCAL_BROWSER';

const baseURL = process.env.MARS_BROWSER_BASE_URL ?? '';

if (!approved) {
  throw new Error('Mars V4 browser execution requires separate explicit approval.');
}

if (!/^http:\/\/127\.0\.0\.1:[0-9]{2,5}\/?$/.test(baseURL)) {
  throw new Error('Mars V4 browser target must be a disposable server on 127.0.0.1.');
}

export default defineConfig({
  testDir: './specs',
  timeout: 20_000,
  retries: 0,
  workers: 1,
  fullyParallel: false,
  forbidOnly: true,
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
