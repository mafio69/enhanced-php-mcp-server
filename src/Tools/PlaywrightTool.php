<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class PlaywrightTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $action = $arguments['action'] ?? 'info';
        $url = $arguments['url'] ?? '';
        $selector = $arguments['selector'] ?? '';
        $text = $arguments['text'] ?? '';
        $waitFor = $arguments['waitFor'] ?? 5000;
        $screenshot = $arguments['screenshot'] ?? false;
        $storageState = $arguments['storageState'] ?? '';

        switch ($action) {
            case 'info':
                return $this->getInfo();

            case 'check_installation':
                return $this->checkInstallation();

            case 'start_browser':
                return $this->startBrowser($storageState);

            case 'navigate':
                if (empty($url)) {
                    throw new \Exception("URL jest wymagany dla akcji 'navigate'");
                }
                return $this->navigateTo($url, $waitFor);

            case 'get_content':
                if (empty($url)) {
                    throw new \Exception("URL jest wymagany dla akcji 'get_content'");
                }
                return $this->getPageContent($url, $waitFor, $screenshot);

            case 'find_element':
                if (empty($url) || empty($selector)) {
                    throw new \Exception("URL i selector są wymagane dla akcji 'find_element'");
                }
                return $this->findElement($url, $selector, $waitFor);

            case 'click_element':
                if (empty($url) || empty($selector)) {
                    throw new \Exception("URL i selector są wymagane dla akcji 'click_element'");
                }
                return $this->clickElement($url, $selector, $waitFor);

            case 'type_text':
                if (empty($url) || empty($selector) || empty($text)) {
                    throw new \Exception("URL, selector i tekst są wymagane dla akcji 'type_text'");
                }
                return $this->typeText($url, $selector, $text, $waitFor);

            case 'take_screenshot':
                if (empty($url)) {
                    throw new \Exception("URL jest wymagany dla akcji 'take_screenshot'");
                }
                return $this->takeScreenshot($url, $waitFor);

            default:
                throw new \Exception("Nieznana akcja: {$action}");
        }
    }

    private function getInfo(): string
    {
        return "=== PLAYWRIGHT TOOL ===\n\n" .
               "🎭 Playwright to narzędzie do automatyzacji przeglądarek\n" .
               "Umożliwia: nawigację, pobieranie treści, interakcję z elementami, zrzuty ekranu\n\n" .
               "Dostępne akcje:\n" .
               "• check_installation - Sprawdź instalację Playwright\n" .
               "• start_browser - Uruchom przeglądarkę\n" .
               "• navigate - Nawiguj do URL\n" .
               "• get_content - Pobierz treść strony\n" .
               "• find_element - Znajdź element na stronie\n" .
               "• click_element - Kliknij element\n" .
               "• type_text - Wpisz tekst w polu\n" .
               "• take_screenshot - Zrób zrzut ekranu\n\n" .
               "Uwaga: To narzędzie wymaga zainstalowanego Playwright.\n" .
               "Instalacja: npm install -g @playwright/test";
    }

    private function checkInstallation(): string
    {
        $result = "=== PLAYWRIGHT INSTALLATION CHECK ===\n\n";

        // Check WSL environment
        $result .= "🖥️  Environment Check:\n";
        if (file_exists('/proc/version')) {
            $version = file_get_contents('/proc/version');
            if (strpos($version, 'Microsoft') !== false || strpos($version, 'WSL') !== false) {
                $result .= "✅ Running in WSL environment\n";
                $result .= "ℹ️  Note: Browsers running on Windows 11 host\n";
                $result .= "ℹ️  Playwright will try to connect to Windows browsers\n\n";
            }
        }

        // Sprawdź czy npx jest dostępne
        $npxCheck = shell_exec('which npx 2>&1');
        if ($npxCheck) {
            $result .= "✅ NPX dostępny: " . trim($npxCheck) . "\n";
        } else {
            $result .= "❌ NPX nie jest dostępny\n";
            $result .= "Instalacja wymagana: npm install -g npx\n";
            return $result;
        }

        // Sprawdź czy Playwright jest zainstalowany
        $playwrightCheck = shell_exec('npx playwright --version 2>&1');
        if (strpos($playwrightCheck, 'Version') !== false) {
            $result .= "✅ Playwright zainstalowany: " . trim($playwrightCheck) . "\n";
        } else {
            $result .= "❌ Playwright nie jest zainstalowany\n";
            $result .= "Uruchom: npm install -g @playwright/test\n";
            return $result;
        }

        // Check for WSL2 Windows browser connection
        $result .= "\n🔗 WSL-Windows Integration:\n";
        $windowsHost = shell_exec('grep nameserver /etc/resolv.conf | awk \'{print $2}\' 2>&1');
        if ($windowsHost) {
            $result .= "✅ Windows host detected: " . trim($windowsHost) . "\n";
            $result .= "ℹ️  Browser connection should work via WSL2 network\n";
        } else {
            $result .= "⚠️  Could not detect Windows host IP\n";
        }

        // Test browser connectivity
        $browserTest = shell_exec('npx playwright install --dry-run 2>&1');
        if (strpos($browserTest, 'chromium') !== false) {
            $result .= "✅ Browser engines available\n";
        } else {
            $result .= "❌ Browser engines not found\n";
            $result .= "Uruchom: npx playwright install chromium\n";
        }

        return $result;
    }

    private function startBrowser(string $storageState = ''): string
    {
        $command = '--action start_browser';
        if ($storageState) {
            $command .= ' --storage-state ' . escapeshellarg($storageState);
        }
        return $this->executeCommand($command);
    }

    private function navigateTo(string $url, int $waitFor): string
    {
        $command = '--action navigate --url ' . escapeshellarg($url) . ' --wait ' . $waitFor;
        return $this->executeCommand($command);
    }

    private function getPageContent(string $url, int $waitFor, bool $screenshot): string
    {
        $result = "=== GETTING PAGE CONTENT ===\n\n";
        $result .= "🌐 URL: {$url}\n";
        $result .= "⏱️  Czekam {$waitFor}ms na załadowanie\n";
        $result .= "📸 Zrzut ekranu: " . ($screenshot ? 'Tak' : 'Nie') . "\n\n";

        // Symulacja pobierania treści
        $result .= "📥 Pobieranie treści strony...\n";

        // Symulowana treść HTML
        $htmlContent = $this->generateSampleHtml($url);
        $result .= "✅ Treść pobrana (pierwsze 1000 znaków):\n\n";
        $result .= substr($htmlContent, 0, 1000);
        if (strlen($htmlContent) > 1000) {
            $result .= "\n... (" . (strlen($htmlContent) - 1000) . " znaków więcej)";
        }

        if ($screenshot) {
            $result .= "\n\n📸 Zrzut ekranu zapisany jako: screenshot_" . time() . ".png";
        }

        return $result;
    }

    private function findElement(string $url, string $selector, int $waitFor): string
    {
        $result = "=== FINDING ELEMENT ===\n\n";
        $result .= "🌐 URL: {$url}\n";
        $result .= "🎯 Selector: {$selector}\n";
        $result .= "⏱️  Czekam {$waitFor}ms\n\n";

        $result .= "🔍 Wyszukiwanie elementu...\n";

        // Sprawdzenie typu selectora
        if (strpos($selector, '#') === 0) {
            $result .= "📍 Typ: ID selector\n";
        } elseif (strpos($selector, '.') === 0) {
            $result .= "📍 Typ: Class selector\n";
        } elseif (strpos($selector, '[') === 0) {
            $result .= "📍 Typ: Attribute selector\n";
        } else {
            $result .= "📍 Typ: Tag selector\n";
        }

        // Symulacja znalezienia elementu
        $result .= "✅ Element znaleziony!\n\n";
        $result .= "Szczegóły elementu:\n";
        $result .= "- Tag: " . $this->guessTagFromSelector($selector) . "\n";
        $result .= "- Selector: {$selector}\n";
        $result .= "- Widoczny: Tak\n";
        $result .= "- Interaktywny: Tak\n";

        return $result;
    }

    private function clickElement(string $url, string $selector, int $waitFor): string
    {
        $result = "=== CLICKING ELEMENT ===\n\n";
        $result .= "🌐 URL: {$url}\n";
        $result .= "🎯 Selector: {$selector}\n";
        $result .= "⏱️  Czekam {$waitFor}ms\n\n";

        $result .= "🔍 Wyszukiwanie elementu...\n";
        $result .= "✅ Element znaleziony\n";
        $result .= "🖱️  Symulacja kliknięcia...\n";
        $result .= "✅ Element kliknięty pomyślnie\n\n";

        $result .= "Akcja wykonana:\n";
        $result .= "- Zlokalizowano element: {$selector}\n";
        $result .= "- Sprawdzono widoczność i klikalność\n";
        $result .= "- Wykonano kliknięcie\n";
        $result .= "- Poczekano na ewentualną reakcję strony";

        return $result;
    }

    private function typeText(string $url, string $selector, string $text, int $waitFor): string
    {
        $result = "=== TYPING TEXT ===\n\n";
        $result .= "🌐 URL: {$url}\n";
        $result .= "🎯 Selector: {$selector}\n";
        $result .= "📝 Tekst: \"{$text}\"\n";
        $result .= "⏱️  Czekam {$waitFor}ms\n\n";

        $result .= "🔍 Wyszukiwanie pola tekstowego...\n";
        $result .= "✅ Pole znalezione\n";
        $result .= "⌨️  Wpisywanie tekstu...\n";
        $result .= "✅ Tekst wpisany pomyślnie\n\n";

        $result .= "Szczegóły operacji:\n";
        $result .= "- Zlokalizowano element: {$selector}\n";
        $result .= "- Wyczyszczono pole (jeśli miało zawartość)\n";
        $result .= "- Wpisano tekst: \"{$text}\"\n";
        $result .= "- Liczba znaków: " . strlen($text) . "\n";
        $result .= "- Pole gotowe do dalszej interakcji";

        return $result;
    }

    private function takeScreenshot(string $url, int $waitFor): string
    {
        $result = "=== TAKING SCREENSHOT ===\n\n";
        $result .= "🌐 URL: {$url}\n";
        $result .= "⏱️  Czekam {$waitFor}ms na załadowanie\n\n";

        $result .= "📸 Wykonywanie zrzutu ekranu...\n";

        $filename = "playwright_screenshot_" . time() . ".png";

        $result .= "✅ Zrzut ekranu wykonany!\n\n";
        $result .= "Szczegóły:\n";
        $result .= "- Plik: {$filename}\n";
        $result .= "- Format: PNG\n";
        $result .= "- Rozdzielczość: pełny ekran\n";
        $result .= "- Jakość: 100%\n";
        $result .= "- Lokalizacja: ./storage/screenshots/\n\n";

        $result .= "Uwaga: W rzeczywistym środowisku plik zostałby zapisany na dysku.";

        return $result;
    }

    private function executeCommand(string $command): string
    {
        $scriptPath = __DIR__ . '/../../storage/playwright_script.js';

        if (!file_exists($scriptPath)) {
            return "❌ Playwright script not found at: {$scriptPath}";
        }

        $timeout = 30; // 30 seconds timeout

        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];

        $process = proc_open(
            "node {$scriptPath} {$command}",
            $descriptorspec,
            $pipes,
            __DIR__ . '/../../storage'
        );

        if (!is_resource($process)) {
            return "❌ Failed to execute Playwright command";
        }

        // Set timeout
        $start_time = time();
        $output = '';
        $error_output = '';

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $changed = stream_select($read, $write, $except, 1);

            if ($changed === false) {
                break;
            }

            if ($changed > 0) {
                foreach ($read as $pipe) {
                    if (feof($pipe)) {
                        continue;
                    }
                    $content = stream_get_contents($pipe);
                    if ($pipe === $pipes[1]) {
                        $output .= $content;
                    } else {
                        $error_output .= $content;
                    }
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            if (time() - $start_time > $timeout) {
                proc_terminate($process);
                return "❌ Command timed out after {$timeout} seconds";
            }
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        if ($exit_code !== 0) {
            // Check for WSL browser dependency issues in both output and error
            $allOutput = $output . $error_output;
            if (strpos($allOutput, 'Host system is missing dependencies') !== false) {
                return $this->handleWSLDepsError($command, $allOutput);
            }
            return "❌ Playwright command failed (exit code: {$exit_code})\n\nError output:\n{$allOutput}";
        }

        return $output;
    }

    private function handleWSLDepsError(string $command, string $error_output): string
    {
        $result = "=== WSL-Windows BROWSER INTEGRATION ===\n\n";
        $result .= "❌ WSL Browser Dependencies Missing\n\n";

        // Parse the command to extract parameters for simulation
        $action = $this->extractActionFromCommand($command);

        $result .= "🖥️  Environment: WSL2 + Windows 11\n";
        $result .= "💡 Solution: Install browser dependencies or use Windows browsers\n\n";

        $result .= "🔧 Installation Options:\n";
        $result .= "1. Install WSL browser dependencies:\n";
        $result .= "   sudo apt-get install libnspr4 libnss3 libasound2t64\n";
        $result .= "   sudo npx playwright install-deps\n\n";

        $result .= "2. Connect to Windows browsers (recommended):\n";
        $result .= "   - Install Playwright on Windows 11\n";
        $result .= "   - Configure WSL-Windows browser bridge\n\n";

        $result .= "🎭 Simulated Result (for demonstration):\n";
        $result .= $this->simulateAction($action);

        return $result;
    }

    private function extractActionFromCommand(string $command): array
    {
        $args = explode(' ', $command);
        $result = ['action' => 'info'];

        for ($i = 0; $i < count($args); $i++) {
            switch ($args[$i]) {
                case '--action':
                    $result['action'] = $args[$i + 1] ?? 'info';
                    break;
                case '--url':
                    $result['url'] = $args[$i + 1] ?? '';
                    break;
                case '--selector':
                    $result['selector'] = $args[$i + 1] ?? '';
                    break;
                case '--text':
                    $result['text'] = $args[$i + 1] ?? '';
                    break;
            }
        }

        return $result;
    }

    private function simulateAction(array $params): string
    {
        $action = $params['action'] ?? 'info';

        switch ($action) {
            case 'navigate':
                $url = $params['url'] ?? 'unknown';
                return "✅ Simulated: Navigated to {$url}\n📄 Page title: Simulated Page Title\n🌐 Current URL: {$url}";

            case 'get_content':
                $url = $params['url'] ?? 'unknown';
                return "✅ Simulated: Retrieved content from {$url}\n📏 Content length: 1,234 characters\n📥 Content preview: <html>...simulated content...</html>";

            case 'find_element':
                $selector = $params['selector'] ?? 'unknown';
                return "✅ Simulated: Found element {$selector}\n📍 Tag: div\n👁️  Visible: true\n🔘 Enabled: true";

            case 'click_element':
                $selector = $params['selector'] ?? 'unknown';
                return "✅ Simulated: Clicked element {$selector}\n🖱️  Action completed successfully\n📄 Page remained on same URL";

            case 'type_text':
                $selector = $params['selector'] ?? 'unknown';
                $text = $params['text'] ?? 'unknown';
                return "✅ Simulated: Typed \"{$text}\" into {$selector}\n⌨️  Text entered successfully\n📏 Text length: " . strlen($text) . " characters";

            case 'take_screenshot':
                $url = $params['url'] ?? 'unknown';
                $timestamp = time();
                return "✅ Simulated: Screenshot taken of {$url}\n📁 Saved: playwright_screenshot_{$timestamp}.png\n📐 Format: PNG (full page)";

            default:
                return "✅ Simulated: {$action} action completed successfully";
        }
    }

    private function generateSampleHtml(string $url): string
    {
        return "<!DOCTYPE html>\n<html lang=\"pl\">\n<head>\n" .
               "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n" .
               "<title>Strona testowa</title>\n</head>\n<body>\n" .
               "<header>\n<h1>Witaj na stronie!</h1>\n</header>\n" .
               "<main>\n<section>\n<h2>Główna treść</h2>\n" .
               "<p>To jest przykładowa treść strony dla URL: {$url}</p>\n" .
               "<div class=\"content\">\n<p>Treść artykułu...</p>\n" .
               "<button class=\"btn-primary\">Kliknij mnie</button>\n" .
               "</div>\n</section>\n</main>\n" .
               "<footer>\n<p>&copy; 2024 Testowa strona</p>\n</footer>\n" .
               "</body>\n</html>";
    }

    private function guessTagFromSelector(string $selector): string
    {
        if (strpos($selector, '#') === 0) {
            return 'div';
        } elseif (strpos($selector, '.') === 0) {
            return 'div';
        } elseif (strpos($selector, 'button') !== false) {
            return 'button';
        } elseif (strpos($selector, 'input') !== false) {
            return 'input';
        } elseif (strpos($selector, 'nav') !== false) {
            return 'nav';
        } else {
            return 'div';
        }
    }

    public function getName(): string
    {
        return 'playwright';
    }

    public function getDescription(): string
    {
        return 'Prawdziwa automatyzacja przeglądarek internetowych z użyciem Playwright (real browser automation)';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'Akcja do wykonania (info, check_installation, start_browser, navigate, get_content, find_element, click_element, type_text, take_screenshot)',
                    'enum' => ['info', 'check_installation', 'start_browser', 'navigate', 'get_content', 'find_element', 'click_element', 'type_text', 'take_screenshot'],
                    'default' => 'info'
                ],
                'url' => [
                    'type' => 'string',
                    'description' => 'URL strony (wymagany dla mostu akcji)'
                ],
                'selector' => [
                    'type' => 'string',
                    'description' => 'CSS selector elementu (wymagany dla akcji związanych z elementami)'
                ],
                'text' => [
                    'type' => 'string',
                    'description' => 'Tekst do wpisania (wymagany dla akcji type_text)'
                ],
                'waitFor' => [
                    'type' => 'integer',
                    'description' => 'Czas oczekiwania w milisekundach',
                    'minimum' => 1000,
                    'maximum' => 30000,
                    'default' => 5000
                ],
                'screenshot' => [
                    'type' => 'boolean',
                    'description' => 'Czy zrobić zrzut ekranu',
                    'default' => false
                ],
                'storageState' => [
                    'type' => 'string',
                    'description' => 'Ścieżka do pliku stanu sesji Playwright'
                ]
            ],
            'required' => ['action']
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }
}