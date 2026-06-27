<?php

return  [
    'server'
     => [
         'name' => 'enhanced-php-mcp-server',
         'version' => '2.1.0',
         'description' => 'Professional PHP MCP Server with multiple tools and Slim Framework',
     ],
    'debug' => false,
    'logging'
     => [
         'enabled' => true,
         'file' => __DIR__ . '/../logs/server.log',
         'level' => 'info',
         'max_files' => 5,
     ],
    'security'
     => [
         'allowed_paths'
          => [
              0 => __DIR__ . '/..',
              1 => __DIR__ . '/../storage',
          ],
         'max_file_size' => 10485760,
     ],
    'http'
     => [
         'timeout' => 10,
         'user_agent' => 'PHP-MCP-Server/2.0',
         'allow_redirects' => true,
     ],
    'tools'
     => [
         'enabled'
          => [
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
          ],
         'restricted'
          => [
              0 => 'write_file',
              1 => 'http_request',
          ],
     ],
    'mcpServers'
     => [
         'Brave-search'
          => [
              'mcpServers'
               => [
                   'brave-search'
                    => [
                        'command' => 'npx',
                        'args'
                         => [
                             0 => '-y',
                             1 => '@brave/brave-search-mcp-server',
                             2 => '--transport',
                             3 => 'http',
                         ],
                        'env'
                         => [
                             'BRAVE_API_KEY' => '${BRAVE_API_KEY}',
                         ],
                    ],
               ],
          ],
     ],
];
