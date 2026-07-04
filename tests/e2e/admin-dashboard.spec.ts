import { test, expect } from '@playwright/test';

test.describe('Zarządzanie Panelem Administratora', () => {

  // Przed każdym testem logujemy się do panelu używając wstrzykniętych w configu haseł testowych
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/login');
    await page.locator('input[name="username"]').fill('testadmin');
    await page.locator('input[name="password"]').fill('testpassword');
    await page.getByRole('button', { name: 'Zaloguj się' }).click();
    
    // Weryfikacja skuteczności logowania
    await expect(page).toHaveURL('/admin/dashboard');
  });

  test('umożliwia nawigację po zakładkach panelu', async ({ page }) => {
    // Jawne kliknięcie zakładki serwerów
    await page.getByText('🖥️ Serwery', { exact: true }).click();
    await expect(page.locator('#servers')).toBeVisible();

    // Przejście do Ustawień
    await page.getByText('⚙️ Ustawienia', { exact: true }).click();
    await expect(page.locator('#settings')).toBeVisible();
    await expect(page.locator('#servers')).not.toBeVisible();
  });

  test('umożliwia dodanie i usunięcie serwera MCP', async ({ page }) => {
    await page.getByText('🖥️ Serwery', { exact: true }).click();

    // Wypełniamy formularz dodawania nowego serwera (format JSON)
    await page.locator('#serverName').fill('test_playwright_mcp');
    await page.locator('#serverJson').fill(`{
      "command": "node",
      "args": ["index.js"]
    }`);
    await page.getByRole('button', { name: 'Dodaj serwer' }).click();
    
    // Weryfikacja powiadomienia o sukcesie
    const resultDiv = page.locator('#result_add_server');
    await expect(resultDiv).toBeVisible({ timeout: 5000 });
    await expect(resultDiv).toContainText('pomyślnie');
    
    // Sprawdzenie czy serwer pojawił się na liście
    const serversList = page.locator('#serversList');
    await expect(serversList).toContainText('test_playwright_mcp');

    // Automatycznie akceptujemy "confirm" podczas usuwania
    page.on('dialog', dialog => dialog.accept());
    
    // Kliknięcie w kosz (usuń) - filtrując po wierszu z tekstem
    await page.locator('#serversList').locator('div').filter({ hasText: 'test_playwright_mcp' }).getByTitle('Usuń serwer').click();
    
    // Upewnienie się, że serwer zniknął z listy
    await expect(serversList).not.toContainText('test_playwright_mcp');
  });

  test('pozwala zmienić hasło administratora w ustawieniach', async ({ page }) => {
    await page.getByText('⚙️ Ustawienia', { exact: true }).click();
    
    // Zmiana testowego hasła na inne
    await page.locator('#oldPassword').fill('testpassword');
    await page.locator('#newPassword').fill('newtestpassword');
    
    await page.getByRole('button', { name: 'Zmień hasło' }).click();

    // Weryfikacja komunikatu
    const resultDiv = page.locator('#result_change_password');
    await expect(resultDiv).toBeVisible({ timeout: 5000 });
    await expect(resultDiv).toContainText('Hasło zmienione pomyślnie');
  });

});
