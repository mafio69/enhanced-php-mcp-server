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
    // Zakładka serwerów (domyślna)
    await expect(page.locator('#serversTab')).toBeVisible();

    // Przejście do Sekretów
    await page.getByText('Sekrety', { exact: true }).click();
    await expect(page.locator('#secretsTab')).toBeVisible();
    await expect(page.locator('#serversTab')).not.toBeVisible();

    // Przejście do Ustawień
    await page.getByText('Ustawienia', { exact: true }).click();
    await expect(page.locator('#settingsTab')).toBeVisible();
  });

  test('umożliwia dodanie i usunięcie serwera MCP', async ({ page }) => {
    // Wypełniamy formularz dodawania nowego serwera
    await page.locator('#serverName').fill('test_playwright_mcp');
    await page.locator('#serverCommand').fill('node');
    await page.locator('#serverArgs').fill('index.js');
    await page.getByRole('button', { name: 'Dodaj serwer' }).click();
    
    // Weryfikacja powiadomienia o sukcesie
    const resultDiv = page.locator('#result_add_server');
    await expect(resultDiv).toBeVisible();
    await expect(resultDiv).toContainText('pomyślnie');
    
    // Sprawdzenie czy serwer pojawił się na liście
    const serversList = page.locator('#serversList');
    await expect(serversList).toContainText('test_playwright_mcp');

    // Automatycznie akceptujemy "confirm" podczas usuwania
    page.on('dialog', dialog => dialog.accept());
    
    // Kliknięcie w kosz (usuń)
    await page.locator('#serversList').getByTitle('Usuń serwer').click();
    
    // Upewnienie się, że serwer zniknął z listy
    await expect(serversList).not.toContainText('test_playwright_mcp');
  });

  test('pozwala zmienić hasło administratora w ustawieniach', async ({ page }) => {
    await page.getByText('Ustawienia', { exact: true }).click();
    
    // Zmiana testowego hasła na inne
    await page.locator('#oldPassword').fill('testpassword');
    await page.locator('#newPassword').fill('newtestpassword');
    await page.locator('#confirmPassword').fill('newtestpassword');
    
    await page.getByRole('button', { name: 'Zmień hasło' }).click();

    // Weryfikacja komunikatu
    const resultDiv = page.locator('#result_change_password');
    await expect(resultDiv).toBeVisible();
    await expect(resultDiv).toContainText('Hasło zmienione pomyślnie');
  });

});
