<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class PHPDiagnosticsTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $result = "=== DIAGNOSTYKA PHP I XDEBUG ===\n\n";

        // PHP Version info
        $result .= "📌 INFORMACJE O PHP:\n";
        $result .= "Wersja PHP: " . PHP_VERSION . "\n";
        $result .= "SAPI: " . PHP_SAPI . "\n";
        $result .= "Ścieżka PHP: " . PHP_BINARY . "\n";
        $result .= "Katalog konfiguracyjny: " . php_ini_loaded_file() . "\n";
        $result .= "Dodatkowe pliki .ini: " . php_ini_scanned_files() . "\n\n";

        // Error logging configuration
        $result .= "📋 KONFIGURACJA LOGOWANIA BŁĘDÓW:\n";
        $result .= "log_errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "\n";
        $result .= "error_log: " . (ini_get('error_log') ?: 'nie ustawiony') . "\n";
        $result .= "error_reporting: " . $this->formatErrorReporting(ini_get('error_reporting')) . "\n";
        $result .= "display_errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "\n";
        $result .= "display_startup_errors: " . (ini_get('display_startup_errors') ? 'On' : 'Off') . "\n\n";

        // Log file paths
        $result .= "📁 ŚCIEŻKI DO LOGÓW:\n";
        $logPaths = $this->getLogPaths();
        foreach ($logPaths as $name => $path) {
            $exists = file_exists($path) ? '✅' : '❌';
            $readable = is_readable($path) ? '✓' : '✗';
            $result .= "{$exists} {$name}: {$path} [{$readable}]\n";
        }
        $result .= "\n";

        // Xdebug configuration
        $result .= "🐛 KONFIGURACJA XDEBUG:\n";
        if (extension_loaded('xdebug')) {
            $xdebugVersion = phpversion('xdebug');
            $result .= "Xdebug zainstalowany: Tak (wersja {$xdebugVersion})\n";
            $result .= "xdebug.mode: " . (ini_get('xdebug.mode') ?: 'nie ustawiony') . "\n";
            $result .= "xdebug.log: " . (ini_get('xdebug.log') ?: 'nie ustawiony') . "\n";
            $result .= "xdebug.log_level: " . (ini_get('xdebug.log_level') ?: 'nie ustawiony') . "\n";
            $result .= "xdebug.start_with_request: " . (ini_get('xdebug.start_with_request') ?: 'nie ustawiony') . "\n";
            $result .= "xdebug.client_host: " . (ini_get('xdebug.client_host') ?: 'nie ustawiony') . "\n";
            $result .= "xdebug.client_port: " . (ini_get('xdebug.client_port') ?: 'nie ustawiony') . "\n";
            $result .= "xdebug.idekey: " . (ini_get('xdebug.idekey') ?: 'nie ustawiony') . "\n";
        } else {
            $result .= "Xdebug zainstalowany: Nie\n";
            $result .= "Instalacja: sudo apt-get install php-xdebug lub pecl install xdebug\n";
        }
        $result .= "\n";

        // Memory and performance
        $result .= "💾 PAMIĘĆ I WYDAJNOŚĆ:\n";
        $result .= "memory_limit: " . ini_get('memory_limit') . "\n";
        $result .= "max_execution_time: " . ini_get('max_execution_time') . "s\n";
        $result .= "max_input_time: " . ini_get('max_input_time') . "s\n";
        $result .= "post_max_size: " . ini_get('post_max_size') . "\n";
        $result .= "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
        $result .= "current_memory_usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
        $result .= "peak_memory_usage: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n\n";

        // Project logs
        $result .= "📂 LOGI PROJEKTU MCP:\n";
        $projectLogs = $this->getProjectLogPaths();
        foreach ($projectLogs as $name => $path) {
            $exists = file_exists($path) ? '✅' : '❌';
            $readable = is_readable($path) ? '✓' : '✗';
            $size = file_exists($path) ? round(filesize($path) / 1024, 1) . ' KB' : '0 KB';
            $result .= "{$exists} {$name}: {$path} [{$size}] [{$readable}]\n";
        }

        $result .= "\n🔍 UWAGI:\n";
        if (!ini_get('log_errors')) {
            $result .= "⚠️  log_errors jest wyłączone - błędy PHP nie są logowane\n";
        }
        if (!ini_get('error_log')) {
            $result .= "⚠️  error_log nie jest ustawiony - błędy nie mają gdzie być zapisywane\n";
        }
        if (!extension_loaded('xdebug')) {
            $result .= "⚠️  Xdebug nie jest zainstalowany - brak debugowania krok po kroku\n";
        }

        return $result;
    }

    private function getLogPaths(): array
    {
        return [
            'PHP Error Log' => ini_get('error_log') ?: '/var/log/php_errors.log',
            'Syslog' => '/var/log/syslog',
            'Apache Error Log' => '/var/log/apache2/error.log',
            'Nginx Error Log' => '/var/log/nginx/error.log',
            'PHP-FPM Log' => '/var/log/php8.3-fpm.log',
            'System Log' => '/var/log/messages',
            'Herd Log' => '/home/mariusz/.config/herd-lite/logs/herd.log'
        ];
    }

    private function getProjectLogPaths(): array
    {
        $baseDir = __DIR__ . '/../..';
        return [
            'MCP Application Log' => $baseDir . '/logs/mcp-server.log',
            'MCP Error Log' => $baseDir . '/logs/error.log',
            'MCP Debug Log' => $baseDir . '/logs/debug.log',
            'Application Logs Dir' => $baseDir . '/logs/',
            'Storage Logs Dir' => $baseDir . '/storage/logs/',
            'Temporary Dir' => $baseDir . '/storage/tmp/'
        ];
    }

    private function formatErrorReporting($value): string
    {
        $levels = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            E_ALL => 'E_ALL'
        ];

        $result = [];
        $value = (int)$value;

        foreach ($levels as $level => $name) {
            if ($value & $level) {
                $result[] = $name;
            }
        }

        return empty($result) ? $value : implode(' | ', $result);
    }

    public function getName(): string {
        return 'php_diagnostics';
    }

    public function getDescription(): string {
        return 'Wyświetla szczegółowe informacje diagnostyczne o PHP, Xdebug i ścieżkach do logów systemowych';
    }

    public function getSchema(): array {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ];
    }

    public function isEnabled(): bool {
        return true;
    }
}