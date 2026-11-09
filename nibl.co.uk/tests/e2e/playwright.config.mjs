import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: '*.spec.mjs',
  timeout: 120_000,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'https://dev.nibl.co.uk',
    // Local dev uses a mkcert-signed cert; the CI/browser context should not
    // fail on it.
    ignoreHTTPSErrors: true,
    headless: true,
    viewport: { width: 1280, height: 900 },
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});