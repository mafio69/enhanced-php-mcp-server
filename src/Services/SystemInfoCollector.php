<?php

namespace App\Services;

use App\ValueObjects\DiskInfo;
use App\ValueObjects\MemoryInfo;
use App\ValueObjects\SystemInfo;

class SystemInfoCollector
{
    public function collect(): SystemInfo
    {
        return new SystemInfo(
            platform: $this->getPlatformInfo(),
            php: $this->getPhpInfo(),
            server: $this->getServerInfo(),
            resources: $this->getResourcesInfo(),
            security: $this->getSecurityInfo()
        );
    }

    private function getPlatformInfo(): array
    {
        return [
            'platform' => PHP_OS,
            'platform_description' => $this->getPlatformDescription(),
            'hostname' => gethostname(),
            'ip_address' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'uptime' => $this->getSystemUptime(),
        ];
    }

    private function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'version_id' => PHP_VERSION_ID,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => $this->getErrorReportingLevel(),
            'extensions' => $this->getImportantExtensions(),
        ];
    }

    private function getServerInfo(): array
    {
        return [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            'port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'gateway_interface' => $_SERVER['GATEWAY_INTERFACE'] ?? 'Unknown',
        ];
    }

    private function getResourcesInfo(): array
    {
        return [
            'memory' => new MemoryInfo(
                current: $this->formatBytes(memory_get_usage(true)),
                peak: $this->formatBytes(memory_get_peak_usage(true)),
                limit: ini_get('memory_limit')
            ),
            'disk' => new DiskInfo(
                total: $this->formatBytes(disk_total_space(__DIR__)),
                used: $this->formatBytes(disk_total_space(__DIR__) - disk_free_space(__DIR__)),
                free: $this->formatBytes(disk_free_space(__DIR__)),
                percentage_used: $this->calculateDiskPercentageUsed()
            ),
            'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
            'processes' => $this->getProcessCount(),
        ];
    }

    private function getSecurityInfo(): array
    {
        return [
            'session_status' => session_status(),
            'session_save_path' => session_save_path(),
            'open_basedir' => ini_get('open_basedir') ?: 'Not set',
            'safe_mode' => ini_get('safe_mode'),
            'file_uploads' => ini_get('file_uploads'),
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'allow_url_include' => ini_get('allow_url_include'),
            'disable_functions' => ini_get('disable_functions') ?: 'None',
        ];
    }

    private function getPlatformDescription(): string
    {
        $platform = PHP_OS;

        if (str_starts_with($platform, 'WIN')) {
            return 'Windows '.php_uname('r');
        } elseif (str_starts_with($platform, 'Darwin')) {
            return 'macOS '.php_uname('r');
        } elseif (str_starts_with($platform, 'Linux')) {
            $distro = $this->getLinuxDistro();
            return 'Linux '.($distro ?: php_uname('r'));
        }

        return $platform.' '.php_uname('r');
    }

    private function getLinuxDistro(): ?string
    {
        $files = [
            '/etc/os-release',
            '/etc/lsb-release',
            '/etc/redhat-release',
            '/etc/debian_version',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    if (preg_match(
                        '/(PRETTY_NAME|ID|DISTRIB_ID|DISTRIB_DESCRIPTION)=["\']?([^"\'\n]+)/',
                        $content,
                        $matches
                    )) {
                        return $matches[2];
                    }
                }
            }
        }

        return null;
    }

    private function getSystemUptime(): string
    {
        if (PHP_OS === 'WIN') {
            if (function_exists('shell_exec')) {
                $uptime = shell_exec('net statistics server | find "Statistics since"');
                if ($uptime) {
                    return trim($uptime);
                }
            }
        } else {
            if (function_exists('shell_exec')) {
                $uptime = shell_exec('uptime');
                if ($uptime) {
                    return trim($uptime);
                }
            }
        }

        return 'Not available';
    }

    private function getErrorReportingLevel(): string
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
        ];

        $errorLevel = error_reporting();
        $result = [];

        foreach ($levels as $level => $name) {
            if ($errorLevel & $level) {
                $result[] = $name;
            }
        }

        return empty($result) ? 'None' : implode(' | ', $result);
    }

    private function getImportantExtensions(): array
    {
        $important = [
            'curl',
            'json',
            'mbstring',
            'openssl',
            'pdo',
            'pdo_mysql',
            'pdo_sqlite',
            'sqlite3',
            'zip',
            'gd',
            'imagick',
            'redis',
            'memcached',
        ];
        $installed = [];

        foreach ($important as $ext) {
            $installed[$ext] = extension_loaded($ext);
        }

        return $installed;
    }

    private function calculateDiskPercentageUsed(): float
    {
        $total = disk_total_space(__DIR__);
        $free = disk_free_space(__DIR__);

        if ($total > 0) {
            $used = $total - $free;
            return round(($used / $total) * 100, 2);
        }

        return 0;
    }

    private function getProcessCount(): ?int
    {
        if (PHP_OS === 'WIN') {
            return null;
        }

        if (function_exists('shell_exec')) {
            $count = shell_exec('ps aux | wc -l');
            if ($count !== null) {
                return (int)trim($count);
            }
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}