<?php

namespace Tests\Integration;

use Exception;
use PHPUnit\Framework\TestCase;

class HTTPAPITest extends TestCase
{
    private string $baseUrl;
    private int $serverPort;

    protected function setUp(): void
    {
        $this->serverPort = $this->findRunningServerPort();

        if ($this->serverPort === null) {
            $this->markTestSkipped('MCP Server is not running on any available port (8888, 8889, 8890)');
        }

        $this->baseUrl = "http://localhost:{$this->serverPort}";
        echo "\n🔧 Using server on port: {$this->serverPort}\n";
    }

    private function findRunningServerPort(): ?int
    {
        $ports = [8888, 8889, 8890]; // Common ports used by start.sh

        foreach ($ports as $port) {
            if ($this->isPortAvailable($port)) {
                return $port;
            }
        }

        return null;
    }

    private function isPortAvailable(int $port): bool
    {
        $socket = @fsockopen('localhost', $port, $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    }

    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true) ?? $response,
        ];
    }

    public function testRootEndpointReturnsServerInfo()
    {
        $result = $this->makeRequest('GET', '/');

        $this->assertEquals(200, $result['status']);
        $this->assertIsArray($result['body']);
        $this->assertArrayHasKey('message', $result['body']);
        $this->assertArrayHasKey('version', $result['body']);
        #TODO nie znana metoda
        $this->assertStringContains('MCP Server', $result['body']['message']);
    }

    /**
     * @throws Exception
     */
    public function testToolsEndpointReturnsListOfTools()
    {
        $result = $this->makeRequest('GET', '/api/tools');

        $this->assertEquals(200, $result['status']);
        $this->assertIsArray($result['body']);
        $this->assertArrayHasKey('tools', $result['body']);
        $this->assertCount(10, $result['body']['tools']);

        $toolNames = array_column($result['body']['tools'], 'name');
        $this->assertContains('hello', $toolNames);
        $this->assertContains('list_files', $toolNames);
        $this->assertContains('read_file', $toolNames);
        $this->assertContains('write_file', $toolNames);
    }

    /**
     * @throws Exception
     */
    public function testStatusEndpointReturnsServerStatus()
    {
        $result = $this->makeRequest('GET', '/api/status');

        $this->assertEquals(200, $result['status']);
        $this->assertIsArray($result['body']);
        $this->assertArrayHasKey('status', $result['body']);
        $this->assertArrayHasKey('server', $result['body']);
        $this->assertArrayHasKey('metrics', $result['body']);
        $this->assertEquals('running', $result['body']['status']);
    }

    /**
     * @throws Exception
     */
    public function testHelloToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'hello',
            'arguments' => ['name' => 'Test User']
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('success', $result['body']);
        $this->assertTrue($result['body']['success']);
        $this->assertArrayHasKey('result', $result['body']);
        #TODO nie znana metoda
        $this->assertStringContains('Test User', $result['body']['result']);
    }

    /**
     * @throws Exception
     */
    public function testGetTimeToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'get_time',
            'arguments' => []
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        #TODO nie znana metoda
        $this->assertStringContains('Aktualny czas:', $result['body']['result']);
    }

    /**
     * @throws Exception
     */
    public function testCalculateToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'calculate',
            'arguments' => [
                'operation' => 'add',
                'a' => 15,
                'b' => 7
            ]
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        #TODO nie znana metoda
        $this->assertStringContains('Wynik: 22', $result['body']['result']);
    }

    public function testListFilesToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'list_files',
            'arguments' => ['path' => '.']
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        #TODO nie znana metoda
        #TODO nie znana metoda
        $this->assertStringContains('Pliki w katalogu: .', $result['body']['result']);
        $this->assertStringContains('composer.json', $result['body']['result']);
        $this->assertStringContains('README.md', $result['body']['result']);
    }

    public function testReadWriteFileWorkflowViaAPI()
    {
        $testContent = 'Integration test content ' . date('Y-m-d H:i:s');
        $testFile = 'integration_test_file.txt';

        try {
            // Write file
            $writeResult = $this->makeRequest('POST', '/api/tools/call', [
                'tool' => 'write_file',
                'arguments' => [
                    'path' => $testFile,
                    'content' => $testContent
                ]
            ]);

            $this->assertEquals(200, $writeResult['status']);
            $this->assertTrue($writeResult['body']['success']);
            #TODO nie znana metoda
            $this->assertStringContains($testFile, $writeResult['body']['result']);

            // Read file back
            $readResult = $this->makeRequest('POST', '/api/tools/call', [
                'tool' => 'read_file',
                'arguments' => ['path' => $testFile]
            ]);

            $this->assertEquals(200, $readResult['status']);
            $this->assertTrue($readResult['body']['success']);
            #TODO nie znana metoda
            $this->assertStringContains($testContent, $readResult['body']['result']);
        } finally {
            // Cleanup
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testSystemInfoToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'system_info',
            'arguments' => []
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        #TODO nie znana metoda
        $this->assertStringContains('=== INFORMACJE O SYSTEMIE ===', $result['body']['result']);
        $this->assertStringContains('Wersja PHP:', $result['body']['result']);
    }

    public function testJsonParseToolViaAPI()
    {
        $jsonData = '{"test": "value", "number": 42}';
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'json_parse',
            'arguments' => ['json' => $jsonData]
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertStringContains('=== SPARSOWANY JSON ===', $result['body']['result']);
        $this->assertStringContains('test', $result['body']['result']);
    }

    public function testGetWeatherToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'get_weather',
            'arguments' => ['city' => 'Kraków']
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertStringContains('=== POGODA DLA MIASTA: KRAKÓW ===', $result['body']['result']);
        $this->assertStringContains('°C', $result['body']['result']);
    }

    public function testUnknownToolReturnsError()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'nonexistent_tool',
            'arguments' => []
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertFalse($result['body']['success']);
        $this->assertArrayHasKey('error', $result['body']);
        $this->assertStringContains('Nieznane narzędzie', $result['body']['error']);
    }

    public function testFileSecurityPreventsPathTraversal()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'read_file',
            'arguments' => ['path' => '../../../etc/passwd']
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertFalse($result['body']['success']);
        $this->assertStringContains('Dostęp do pliku zabroniony', $result['body']['error']);
    }

    public function testMetricsEndpointReturnsData()
    {
        $result = $this->makeRequest('GET', '/api/metrics');

        $this->assertEquals(200, $result['status']);
        $this->assertIsArray($result['body']);
        // Metrics structure may vary, just check it's not empty
        $this->assertNotEmpty($result['body']);
    }

    public function testLogsEndpointReturnsData()
    {
        $result = $this->makeRequest('GET', '/api/logs');

        $this->assertEquals(200, $result['status']);
        $this->assertIsArray($result['body']);
        $this->assertArrayHasKey('logs', $result['body']);
        $this->assertIsArray($result['body']['logs']);
    }

    /**
     * @dataProvider toolParameterValidationProvider
     */
    public function testToolParameterValidation($tool, $arguments, $expectedError)
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => $tool,
            'arguments' => $arguments
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertFalse($result['body']['success']);
        $this->assertStringContains($expectedError, $result['body']['error']);
    }

    public static function toolParameterValidationProvider()
    {
        return [
            'calculate missing operation' => [
                'calculate',
                ['a' => 5, 'b' => 3],
                'Nieznana operacja'
            ],
            'read file empty path' => [
                'read_file',
                ['path' => ''],
                'Ścieżka do pliku jest wymagana'
            ],
            'write file empty path' => [
                'write_file',
                ['path' => '', 'content' => 'test'],
                'Ścieżka do pliku jest wymagana'
            ],
            'get weather empty city' => [
                'get_weather',
                ['city' => ''],
                'Nazwa miasta jest wymagana'
            ],
        ];
    }
}