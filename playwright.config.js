// playwright.config.js
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/E2E',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        channel: 'chrome',
        launchOptions: {
          args: ['--no-sandbox', '--no-first-run', '--no-default-browser-check'],
        },
      },
    },
  ],
  webServer: {
    // Тесты должны быть самодостаточными и не зависеть от запущенных вручную
    // процессов на :8000. Поэтому сначала собираем бандл с VITE_APP_URL=:8080,
    // затем поднимаем свой сервер на :8080.
    command:
      'npm run build:e2e && php artisan serve --host=127.0.0.1 --port=8080 --quiet',
    url: 'http://127.0.0.1:8080',
    timeout: 180 * 1000,
    reuseExistingServer: true,
  },
});
