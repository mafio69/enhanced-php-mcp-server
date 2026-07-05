import { defineConfig, devices } from '@playwright/test';
import path from 'path';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  retries: process.env.CI ? 2 : 0,
  workers: 1, // Ważne: PHP wbudowany webserver obsługuje domyślnie jedno żądanie na raz
  reporter: [['html', { outputFolder: '.local' }]],
  use: {
    baseURL: 'http://localhost:8799',
    trace: 'on-first-retry',
    headless: true,
    viewport: { width: 1280, height: 720 },
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    // Uruchamia z wyizolowaną pamięcią hasła testowego admina!
    command: 'ADMIN_USERNAME="testadmin" ADMIN_PASSWORD="testpassword" ADMIN_PASSWORD_FILE="storage/.test_admin_password" php -S 127.0.0.1:8799 -t public public/index.php',
    url: 'http://127.0.0.1:8799',
    reuseExistingServer: !process.env.CI,
    timeout: 10000,
  },
});
