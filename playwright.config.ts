import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 60000,
  expect: { timeout: 10000 },
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: [['list', { printSteps: true }]],
  use: {
    baseURL: 'http://localhost:8080',
    headless: false,
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
    viewport: { width: 1440, height: 900 },
    launchOptions: {
      args: [
        '--window-position=1140,0',
        '--window-size=1440,900',
        '--no-first-run',
        '--no-default-browser-check',
      ],
    },
  },
});
