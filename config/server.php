<?php

return [
    'server' => [
        'name' => 'enhanced-php-mcp-server',
        'version' => '2.1.0',
        'description' => 'Professional PHP MCP Server with multiple tools and Slim Framework',
    ],

    'debug' => false,

    'logging' => [
        'enabled' => true,
        'file' => __DIR__ . '/../logs/server.log',
        'level' => 'info',
        'max_files' => 5,
    ],

    'security' => [
        'allowed_paths' => [
            __DIR__ . '/..',
            __DIR__ . '/../storage',
        ],
        'max_file_size' => 10 * 1024 * 1024, // 10MB
    ],

    'http' => [
        'timeout' => 10,
        'user_agent' => 'PHP-MCP-Server/2.0',
        'allow_redirects' => true,
    ],

    'tools' => [
        'enabled' => [
            'hello',
            'get_time',
            'calculate',
            'read_file',
            'write_file',
            'list_files',
            'system_info',
            'http_request',
            'json_parse',
            'get_weather',
        ],
        'restricted' => [
            'write_file',
            'http_request',
        ],
    ],
];