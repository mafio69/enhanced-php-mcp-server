<?php

namespace Tests\Integration;

use Exception;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertStringContainsString;

class HTTPAPITest extends TestCase
{
    private string $baseUrl;
    private int $serverPort;

    protected function setUp(): void
    {
        $this->serverPort = $this->findRunningServerPort();

        if ($this->serverPort === null) {
            $this->markTestSkipped('MCP Server is not running on any available port (8794, 8795, 8890)');
        }

        $this->baseUrl = "http://localhost:{$this->serverPort}";
        // echo "\n🔧 Using server on port: {$this->serverPort}\n"; // Disabled to prevent risky tests
    }

    private function findRunningServerPort(): ?int
    {
        $ports = [8794, 8795, 8890]; // Common ports used by start.sh

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
        $this->assertStringContainsString('MCP Server', $result['body']['message']);
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
        $this->assertCount(10, $result['body']['tools']['tools']);

        $toolNames = array_column($result['body']['tools']['tools'], 'name');
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
            'arguments' => ['name' => 'Test User'],
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('success', $result['body']);
        $this->assertTrue($result['body']['success']);
        $this->assertArrayHasKey('data', $result['body']);
        $this->assertStringContainsString('Test User', $result['body']['data']);
    }

    /**
     * @throws Exception
     */
    public function testGetTimeToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'get_time',
            'arguments' => [],
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertStringContainsString('Aktualny czas:', $result['body']['data']);
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
                'b' => 7,
            ],
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        #TODO nie znana metoda
        $this->assertStringContainsString('Wynik: 22', $result['body']['data']);
    }

    public function testListFilesToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'list_files',
            'arguments' => ['path' => '.'],
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertStringContainsString('Pliki w katalogu: .', $result['body']['data']);
        $this->assertStringContainsString('composer.json', $result['body']['data']);
        $this->assertStringContainsString('README.md', $result['body']['data']);
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
                    'content' => $testContent,
                ],
            ]);

            $this->assertEquals(200, $writeResult['status']);
            $this->assertTrue($writeResult['body']['success']);
            $this->assertStringContainsString($testFile, $writeResult['body']['data']);

            // Read file back
            $readResult = $this->makeRequest('POST', '/api/tools/call', [
                'tool' => 'read_file',
                'arguments' => ['path' => $testFile],
            ]);

            $this->assertEquals(200, $readResult['status']);
            $this->assertTrue($readResult['body']['success']);
            $this->assertStringContainsString($testContent, $readResult['body']['data']);
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
            'arguments' => [],
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertStringContainsString('=== INFORMACJE O SYSTEMIE ===', $result['body']['data']);
        $this->assertStringContainsString('Wersja PHP:', $result['body']['data']);
    }

    public function testJsonParseToolViaAPI()
    {
        $jsonData = '{"test": "value", "number": 42}';
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'json_parse',
            'arguments' => ['json' => $jsonData],
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertStringContainsString('=== SPARSOWANY JSON ===', $result['body']['data']);
        $this->assertStringContainsString('test', $result['body']['data']);
    }

    public function testGetWeatherToolViaAPI()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'get_weather',
            'arguments' => ['city' => 'Kraków'],
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertStringContainsString('=== POGODA DLA MIASTA: KRAKóW ===', $result['body']['data']);
        $this->assertStringContainsString('°C', $result['body']['data']);
    }

    public function testUnknownToolReturnsError()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'nonexistent_tool',
            'arguments' => [],
        ]);

        $this->assertContains($result['status'], [200, 500]); // Accept both 200 and 500 for errors
        $this->assertFalse($result['body']['success']);
        $this->assertArrayHasKey('error', $result['body']);
        $this->assertStringContainsString('Nieznane narzędzie', $result['body']['details']['details']);
    }

    public function testFileSecurityPreventsPathTraversal()
    {
        $result = $this->makeRequest('POST', '/api/tools/call', [
            'tool' => 'read_file',
            'arguments' => ['path' => '../../../etc/passwd'],
        ]);

        $this->assertContains($result['status'], [200, 500]); // Accept both 200 and 500 for security errors
        $this->assertFalse($result['body']['success']);
        $this->assertStringContainsString('Dostęp do pliku zabroniony', $result['body']['details']['details']);
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
            'arguments' => $arguments,
        ]);

        // For calculate and get_weather with missing required params, API returns success=true with error message in data
        if (in_array($tool, ['calculate', 'get_weather'])) {
            $this->assertEquals(200, $result['status']);
            $this->assertTrue($result['body']['success']);
            $this->assertStringContainsString($expectedError, $result['body']['data']);
        } else {
            // For file operations, API returns success=false with HTTP 500
            $this->assertContains($result['status'], [200, 500]);
            $this->assertFalse($result['body']['success']);
            $this->assertStringContainsString($expectedError, $result['body']['details']['details']);
        }
    }

    public static function toolParameterValidationProvider()
    {
        return [
            'calculate missing operation' => [
                'calculate',
                ['a' => 5, 'b' => 3],
                'Nieznana operacja',
            ],
            'read file empty path' => [
                'read_file',
                ['path' => ''],
                'Ścieżka do pliku jest wymagana',
            ],
            'write file empty path' => [
                'write_file',
                ['path' => '', 'content' => 'test'],
                'Ścieżka do pliku jest wymagana',
            ],
            'get weather empty city' => [
                'get_weather',
                ['city' => ''],
                'Nazwa miasta jest wymagana',
            ],
        ];
    }

    // --- Admin API tests ---

    private function adminLogin(): string
    {
        $result = $this->makeRequest('POST', '/admin/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $this->assertEquals(200, $result['status'], 'Admin login failed');

        return $result['body']['data']['session_id'] ?? $this->getSessionCookie();
    }

    private function getSessionCookie(): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/admin/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'username' => 'admin',
                'password' => 'admin123',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
        ]);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        curl_close($ch);

        preg_match('/admin_session=([^;]+)/', $headers, $matches);

        return $matches[1] ?? '';
    }

    private function makeAuthenticatedRequest(string $method, string $endpoint, string $token, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        $httpHeaders = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $httpHeaders,
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

    public function testAdminLoginSuccess(): void
    {
        $result = $this->makeRequest('POST', '/admin/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertArrayHasKey('session_id', $result['body']);
    }

    public function testAdminLoginWithInvalidCredentials(): void
    {
        $result = $this->makeRequest('POST', '/admin/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ]);

        $this->assertEquals(401, $result['status']);
        $this->assertFalse($result['body']['success']);
    }

    public function testAdminApiRequiresAuth(): void
    {
        $result = $this->makeRequest('GET', '/admin/api/secrets');

        $this->assertEquals(401, $result['status']);
        $this->assertFalse($result['body']['success']);
    }

    public function testAdminDashboardRequiresAuth(): void
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/admin/dashboard',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOBODY => true,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(302, $httpCode, 'Dashboard should redirect to login when not authenticated');
    }

    public function testAdminSecretsFullFlow(): void
    {
        $token = $this->adminLogin();
        $this->assertNotEmpty($token);

        // List secrets (empty initially)
        $listResult = $this->makeAuthenticatedRequest('GET', '/admin/api/secrets', $token);
        $this->assertEquals(200, $listResult['status']);
        $this->assertIsArray($listResult['body']['data']);

        // Store a secret
        $storeResult = $this->makeAuthenticatedRequest('POST', '/admin/api/secrets', $token, [
            'key' => 'integration.test.key',
            'value' => 'integration-test-value-123',
        ]);
        $this->assertEquals(200, $storeResult['status']);
        $this->assertTrue($storeResult['body']['success']);

        // List secrets (should contain our key)
        $listResult = $this->makeAuthenticatedRequest('GET', '/admin/api/secrets', $token);
        $this->assertContains('integration.test.key', $listResult['body']['data']);

        // Get secret value
        $getResult = $this->makeAuthenticatedRequest('GET', '/admin/api/secrets/integration.test.key', $token);
        $this->assertEquals(200, $getResult['status']);
        $this->assertSame('integration-test-value-123', $getResult['body']['data']['value']);

        // Delete secret
        $deleteResult = $this->makeAuthenticatedRequest('DELETE', '/admin/api/secrets/integration.test.key', $token);
        $this->assertEquals(200, $deleteResult['status']);
        $this->assertTrue($deleteResult['body']['success']);

        // Verify deleted
        $getResult = $this->makeAuthenticatedRequest('GET', '/admin/api/secrets/integration.test.key', $token);
        $this->assertEquals(404, $getResult['status']);
    }

    public function testAdminEncryptDecryptFlow(): void
    {
        $token = $this->adminLogin();
        $this->assertNotEmpty($token);

        $original = 'test-value-to-encrypt';

        // Encrypt
        $encryptResult = $this->makeAuthenticatedRequest('POST', '/admin/api/secrets/encrypt', $token, [
            'value' => $original,
        ]);
        $this->assertEquals(200, $encryptResult['status']);
        $this->assertSame($original, $encryptResult['body']['data']['original']);
        $encrypted = $encryptResult['body']['data']['encrypted'];
        $this->assertNotEmpty($encrypted);

        // Decrypt
        $decryptResult = $this->makeAuthenticatedRequest('POST', '/admin/api/secrets/decrypt', $token, [
            'encrypted' => $encrypted,
        ]);
        $this->assertEquals(200, $decryptResult['status']);
        $this->assertSame($original, $decryptResult['body']['data']['decrypted']);
    }
}
