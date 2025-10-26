<?php

namespace App;

use Psr\Log\LogLevel;
use Stringable;

class Logger
{
    private string $logFile;
    private string $level;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'file' => __DIR__.'/../logs/server.log',
            'level' => 'info',
            'max_size' => 10 * 1024 * 1024, // 10MB
            'backup_count' => 5,
            'date_format' => 'Y-m-d H:i:s',
            'log_format' => '[{date}] {level}: {message} {context}',
        ], $config);

        $this->logFile = $this->config['file'];
        $this->level = $this->config['level'];

        // Upewnij się, że katalog logów istnieje
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Rotacja logów jeśli plik jest za duży
        $this->rotateIfNeeded();
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $date = date($this->config['date_format']);
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $logMessage = str_replace(
            ['{date}', '{level}', '{message}', '{context}'],
            [$date, strtoupper($level), (string)$message, $contextStr],
            $this->config['log_format']
        );

        $this->writeToFile($logMessage);
    }

    private function shouldLog(string $level): bool
    {
        $levels = [
            LogLevel::DEBUG => 0,
            LogLevel::INFO => 1,
            LogLevel::NOTICE => 2,
            LogLevel::WARNING => 3,
            LogLevel::ERROR => 4,
            LogLevel::CRITICAL => 5,
            LogLevel::ALERT => 6,
            LogLevel::EMERGENCY => 7,
        ];

        $currentLevel = $levels[$this->level] ?? 1;
        $messageLevel = $levels[$level] ?? 1;

        return $messageLevel >= $currentLevel;
    }

    private function writeToFile(string $message): void
    {
        file_put_contents($this->logFile, $message.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) < $this->config['max_size']) {
            return;
        }

        // Usuń najstarsze logi
        for ($i = $this->config['backup_count']; $i > 0; $i--) {
            $oldFile = $this->logFile.'.'.$i;
            $newFile = $this->logFile.'.'.($i + 1);

            if (file_exists($oldFile)) {
                if ($i === $this->config['backup_count']) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Przesuń obecny log
        rename($this->logFile, $this->logFile.'.1');
    }

    public function getLogPath(): string
    {
        return $this->logFile;
    }

    public static function createFromConfig(array $config): self
    {
        return new self($config['logging'] ?? []);
    }
}