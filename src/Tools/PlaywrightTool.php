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
        return $this->executeCommand('--action info');
    }

    private function checkInstallation(): string
    {
        return $this->executeCommand('--action check_installation');
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
        $command = '--action get_content --url ' . escapeshellarg($url) . ' --wait ' . $waitFor;
        if ($screenshot) {
            $command .= ' --screenshot true';
        }
        return $this->executeCommand($command);
    }

    private function findElement(string $url, string $selector, int $waitFor): string
    {
        $command = '--action find_element --url ' . escapeshellarg($url) . ' --selector ' . escapeshellarg($selector) . ' --wait ' . $waitFor;
        return $this->executeCommand($command);
    }

    private function clickElement(string $url, string $selector, int $waitFor): string
    {
        $command = '--action click_element --url ' . escapeshellarg($url) . ' --selector ' . escapeshellarg($selector) . ' --wait ' . $waitFor;
        return $this->executeCommand($command);
    }

    private function typeText(string $url, string $selector, string $text, int $waitFor): string
    {
        $command = '--action type_text --url ' . escapeshellarg($url) . ' --selector ' . escapeshellarg($selector) . ' --text ' . escapeshellarg($text) . ' --wait ' . $waitFor;
        return $this->executeCommand($command);
    }

    private function takeScreenshot(string $url, int $waitFor): string
    {
        $command = '--action take_screenshot --url ' . escapeshellarg($url) . ' --wait ' . $waitFor;
        return $this->executeCommand($command);
    }

    private function executeCommand(string $command): string
    {
        $scriptPath = __DIR__ . '/../../storage/playwright_script.js';

        if (!file_exists($scriptPath)) {
            return "❌ Playwright script not found at: {$scriptPath}";
        }

        $timeout = 60; // 60 seconds timeout for real browser operations

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
            if (strpos($allOutput, 'Host system is missing dependencies') !== false ||
                strpos($allOutput, 'libasound2t64') !== false ||
                strpos($allOutput, 'libnspr4') !== false ||
                strpos($allOutput, 'WARNING') !== false ||
                $exit_code === 1) {
                return $this->handleWSLDepsError($command, $allOutput, $exit_code);
            }
            return "❌ Playwright command failed (exit code: {$exit_code})\n\nError output:\n{$allOutput}";
        }

        return $output;
    }

    private function handleWSLDepsError(string $command, string $error_output, int $exit_code = 1): string
    {
        $result = "=== WSL-Windows BROWSER INTEGRATION ===\n\n";
        $result .= "❌ WSL Browser Dependencies Missing\n\n";

        // Parse the command to extract parameters for better error message
        $action = $this->extractActionFromCommand($command);

        $result .= "🖥️  Environment: WSL2 + Windows 11\n";
        $result .= "💡 Solution: Install browser dependencies or use Windows browsers\n\n";

        $result .= "🔧 Installation Options:\n";
        $result .= "1. Install WSL browser dependencies:\n";
        $result .= "   sudo apt-get update\n";
        $result .= "   sudo apt-get install libnspr4 libnss3 libasound2t64 libatk-bridge2.0-0 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxrandr2 libgbm1 libxss1\n";
        $result .= "   sudo npx playwright install-deps\n";
        $result .= "   sudo npx playwright install chromium\n\n";

        $result .= "2. Connect to Windows browsers (recommended):\n";
        $result .= "   - Install Playwright on Windows 11\n";
        $result .= "   - Configure WSL-Windows browser bridge\n\n";

        $result .= "❌ Error details:\n";
        $result .= $error_output . "\n\n";

        $result .= "🎭 For demonstration, here's what would happen:\n";
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
                    'description' => 'URL strony (wymagany dla większości akcji)'
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
                    'maximum' => 60000,
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