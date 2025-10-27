<?php

/**
 * MCP PHP Server Database Configuration
 */

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'mcp_php_server',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ],
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/../storage/database.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'path' => __DIR__ . '/../migrations',
    ],

    // Database logging configuration
    'logging' => [
        'enabled' => true,
        'table' => 'system_logs',
        'level' => 'info',
        'channels' => ['mcp-server', 'auth', 'tools', 'secrets'],
    ],

    // Connection pool settings
    'pool' => [
        'max_connections' => 100,
        'timeout' => 60,
        'retry_times' => 3,
    ],

    // Cache settings for queries
    'cache' => [
        'enabled' => false,
        'ttl' => 3600, // 1 hour
        'prefix' => 'mcp_db_',
    ],

    // Backup settings
    'backup' => [
        'enabled' => true,
        'path' => __DIR__ . '/../storage/backups',
        'schedule' => 'daily',
        'retention' => 7, // days
    ],

    // Performance settings
    'performance' => [
        'slow_query_log' => true,
        'slow_query_threshold' => 1.0, // seconds
        'query_cache' => true,
        'index_hints' => true,
    ],
];