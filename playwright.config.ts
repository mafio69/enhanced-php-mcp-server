import { defineConfig, devices } from '@playwright/test';

/**
 * Zobacz https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: './tests/e2e',
  /* Uruchom testy równolegle */
  fullyParallel: true,
  /* Nie uruchamiaj testu na nowo gdy wyskoczy error (chyba że w CI) */
  retries: process.env.CI ? 2 : 0,
  /* Ograniczenie workerów (ze względu na to, że PHP server może być 1-wątkowy lokalnie) */
  workers: 1,
  /* Raporter pokazujący wyniki w terminalu i tworzący raport HTML */
  reporter: 'html',
  /* Konfiguracja głównego środowiska przeglądarki */
  use: {
    /* Główny adres testowanego serwera WWW */
    baseURL: 'http://localhost:8795',
    /* Zapisywanie tzw. trace'ów (zrzutów pełnego stanu) przy każdym niepowodzeniu. */
    trace: 'on-first-retry',
    /* Uruchamianie w trybie bez okien (headless), odpowiednie dla AI */
    headless: true,
    /* Wymiary ekranu typowego desktopa */
    viewport: { width: 1280, height: 720 },
  },

  /* Konfiguracja konkretnej przeglądarki (w tym wypadku tylko stabilne Chromium) */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
