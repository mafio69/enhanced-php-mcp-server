import { test, expect } from '@playwright/test';

test.describe('Strona Startowa i Podstawowa Nawigacja', () => {

  test('powinna ładować główną stronę i wyświetlać odpowiednie sekcje', async ({ page }) => {
    // Wejście na główny adres URL projektu
    await page.goto('/');

    // Oczekujemy, że strona posiada poprawny tytuł
    await expect(page).toHaveTitle(/MCP PHP Server/);

    // Szukamy nagłówka głównego H1
    const heading = page.locator('h1', { hasText: 'MCP PHP Server' });
    await expect(heading).toBeVisible();

    // Sprawdzamy czy wskaźnik statusu pokazuje "Server Online" lub "Server Issues" (zależnie od API)
    const statusIndicator = page.locator('.status-indicator span');
    await expect(statusIndicator).toBeVisible();
    
    // Sprawdzamy obecność przycisku wywołującego podgląd narzędzi
    const viewToolsBtn = page.getByRole('button', { name: '🔧 View Tools' });
    await expect(viewToolsBtn).toBeVisible();
  });

  test('powinna obsługiwać przekierowania w przypadku braku sesji do panelu admina', async ({ page }) => {
    // Próba wejścia na panel admina
    await page.goto('/admin/dashboard');

    // System powinien przekierować na logowanie (weryfikacja czy zmiana middleware po stronie serwera/Dockera działa)
    await expect(page).toHaveURL(/\/admin\/login/);

    // Ekran logowania powinien wyświetlić odpowiednie pole
    const usernameInput = page.locator('input[name="username"]');
    await expect(usernameInput).toBeVisible();
  });
});
