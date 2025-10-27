<?php

return array (
  'server' => 
  array (
    'name' => 'enhanced-php-mcp-server',
    'version' => '2.1.0',
    'description' => 'Professional PHP MCP Server with multiple tools and Slim Framework',
  ),
  'debug' => false,
  'logging' => 
  array (
    'enabled' => true,
    'file' => '/home/mariusz/mcp-php-server/config/../logs/server.log',
    'level' => 'info',
    'max_files' => 5,
  ),
  'security' => 
  array (
    'allowed_paths' => 
    array (
      0 => '/home/mariusz/mcp-php-server/config/..',
      1 => '/home/mariusz/mcp-php-server/config/../storage',
    ),
    'max_file_size' => 10485760,
  ),
  'http' => 
  array (
    'timeout' => 10,
    'user_agent' => 'PHP-MCP-Server/2.0',
    'allow_redirects' => true,
  ),
  'tools' =>
  array (
    'enabled' =>
    array (
      0 => 'hello',
      1 => 'get_time',
      2 => 'calculate',
      3 => 'read_file',
      4 => 'write_file',
      5 => 'list_files',
      6 => 'system_info',
      7 => 'http_request',
      8 => 'json_parse',
      9 => 'get_weather',
      10 => 'brave_search',
      11 => 'playwright',
    ),
    'restricted' =>
    array (
      0 => 'write_file',
      1 => 'http_request',
      2 => 'brave_search',
    ),
  ),
  'mcpServers' =>
  array (
    'Brave-search' =>
    array (
      'mcpServers' =>
      array (
        'brave-search' =>
        array (
          'command' => 'npx',
          'args' =>
          array (
            0 => '-y',
            1 => '@brave/brave-search-mcp-server',
            2 => '--transport',
            3 => 'http',
          ),
          'env' =>
          array (
            'BRAVE_API_KEY' => '${BRAVE_API_KEY}',
          ),
        ),
      ),
    ),
    'playwright' =>
    array (
      'mcpServers' =>
      array (
        'playwright' =>
        array (
          'command' => 'npx',
          'args' =>
          array (
            0 => '@playwright/mcp@latest',
            1 => '--isolated',
            2 => '--storage-state=/home/mariusz/mcp-php-server/storage/playwright-storage.json',
          ),
        ),
      ),
    ),
  ),
);
