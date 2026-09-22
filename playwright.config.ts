import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Playwright',
  timeout: 30_000,
  fullyParallel: true,
  retries: 0,
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:8094',
    trace: 'on-first-retry',
    headless: true,
  },
  webServer: {
    command: 'php -S 127.0.0.1:8094 -t public public/index.php',
    port: 8094,
    reuseExistingServer: false,
    timeout: 30_000,
    env: {
      APP_ENV: 'test',
      APP_DEBUG: '0',
    },
  },
  projects: [{ name: 'api', use: {} }],
});
