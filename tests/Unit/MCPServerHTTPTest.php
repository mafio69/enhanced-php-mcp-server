<?php

namespace Tests\Unit;

use App\MCPServerHTTP;
use App\Services\MonitoringService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use function PHPUnit\Framework\assertStringContainsString;

class MCPServerHTTPTest extends TestCase
{
    private MCPServerHTTP $server;
    private $loggerMock;
    private $monitoringMock;

    protected function setUp(): void
    {
        // Mock dependencies
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->monitoringMock = $this->createMock(MonitoringService::class);

        $this->server = new MCPServerHTTP($this->loggerMock, $this->monitoringMock);
    }

    public function testGetToolsReturnsAllRegisteredTools()
    {
        $tools = $this->server->getTools();

        $this->assertIsArray($tools);
        $this->assertArrayHasKey('tools', $tools);
        $this->assertCount(10, $tools['tools']);

        $toolNames = array_column($tools['tools'], 'name');
        $expectedTools = [
            'hello', 'get_time', 'calculate', 'list_files',
            'read_file', 'write_file', 'system_info',
            'json_parse', 'http_request', 'get_weather'
        ];

        foreach ($expectedTools as $toolName) {
            $this->assertContains($toolName, $toolNames, "Missing tool: $toolName");
        }
    }

    public function testHelloToolReturnsGreeting()
    {
        $result = $this->server->executeTool('hello', ['name' => 'Jan']);
        $this->assertEquals('Cześć, Jan! Miło Cię poznać.', $result);
    }

    public function testHelloToolWithDefaultName()
    {
        $result = $this->server->executeTool('hello', []);
        $this->assertEquals('Cześć, Nieznajomy! Miło Cię poznać.', $result);
    }

    public function testGetTimeReturnsCurrentTime()
    {
        $result = $this->server->executeTool('get_time', []);
        $this->assertStringContainsString('Aktualny czas:', $result);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result);
    }

    /**
     * @dataProvider calculateProvider
     */
    public function testCalculateTool($operation, $a, $b, $expected)
    {
        $result = $this->server->executeTool('calculate', [
            'operation' => $operation,
            'a' => $a,
            'b' => $b
        ]);

        $this->assertEquals("Wynik: $expected", $result);
    }

    public static function calculateProvider()
    {
        return [
            ['add', 5, 3, 8],
            ['subtract', 10, 4, 6],
            ['multiply', 6, 7, 42],
            ['divide', 20, 4, 5],
        ];
    }

    public function testCalculateToolDivisionByZero()
    {
        $result = $this->server->executeTool('calculate', [
            'operation' => 'divide',
            'a' => 10,
            'b' => 0
        ]);

        $this->assertEquals('Błąd: Dzielenie przez zero', $result);
    }

    public function testCalculateToolUnknownOperation()
    {
        $result = $this->server->executeTool('calculate', [
            'operation' => 'unknown',
            'a' => 1,
            'b' => 2
        ]);

        $this->assertEquals('Nieznana operacja: unknown', $result);
    }

    public function testListFilesWithCurrentDirectory()
    {
        $result = $this->server->executeTool('list_files', ['path' => '.']);

        $this->assertStringContainsString('Pliki w katalogu: .', $result);
        $this->assertStringContainsString('[FILE] composer.json', $result);
        $this->assertStringContainsString('[FILE] README.md', $result);
        $this->assertStringContainsString('[DIR] src', $result);
    }

    public function testListFilesWithSubdirectory()
    {
        $result = $this->server->executeTool('list_files', ['path' => 'src']);

        $this->assertStringContainsString('Pliki w katalogu: src', $result);
        $this->assertStringContainsString('[FILE] MCPServerHTTP.php', $result);
    }

    public function testListFilesWithDefaultPath()
    {
        $result = $this->server->executeTool('list_files', []);

        $this->assertStringContainsString('Pliki w katalogu: .', $result);
    }

    public function testReadFileWithValidFile()
    {
        $result = $this->server->executeTool('read_file', ['path' => 'composer.json']);

        $this->assertStringContainsString('Plik: composer.json', $result);
        $this->assertStringContainsString('Rozmiar:', $result);
        $this->assertStringContainsString('Zawartość:', $result);
        $this->assertStringContainsString('"name":', $result); // JSON content
    }

    public function testReadFileWithNonExistentFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dostęp do pliku zabroniony! Ścieżka wykracza poza dozwolony katalog.');

        $this->server->executeTool('read_file', ['path' => 'non_existent_file.txt']);
    }

    public function testWriteFileCreatesAndReadsFile()
    {
        $testContent = 'Test content from unit test';
        $testFile = 'test_unit_write.txt';

        // Write file
        $writeResult = $this->server->executeTool('write_file', [
            'path' => $testFile,
            'content' => $testContent
        ]);

        $this->assertStringContainsString("Zapisano plik: $testFile", $writeResult);
        $this->assertStringContainsString('Zapisano bajtów:', $writeResult);

        // Read file back
        $readResult = $this->server->executeTool('read_file', ['path' => $testFile]);
        $this->assertStringContainsString($testContent, $readResult);

        // Cleanup
        unlink($testFile);
    }

    public function testSystemInfoReturnsValidInfo()
    {
        $result = $this->server->executeTool('system_info', []);

        $this->assertStringContainsString('=== INFORMACJE O SYSTEMIE ===', $result);
        $this->assertStringContainsString('System operacyjny:', $result);
        $this->assertStringContainsString('Wersja PHP:', $result);
        $this->assertStringContainsString('Architektura:', $result);
        $this->assertStringContainsString('Hostname:', $result);
    }

    public function testJsonParseWithValidJson()
    {
        $jsonString = '{"name": "test", "value": 123, "active": true}';
        $result = $this->server->executeTool('json_parse', ['json' => $jsonString]);

        $this->assertStringContainsString('=== SPARSOWANY JSON ===', $result);
        $this->assertStringContainsString('Typ główny: array', $result);
        $this->assertStringContainsString('Liczba elementów: 3', $result);
        $this->assertStringContainsString('"name": "test"', $result);
    }

    public function testJsonParseWithInvalidJson()
    {
        $invalidJson = '{"name": "test", "value":}';
        $result = $this->server->executeTool('json_parse', ['json' => $invalidJson]);

        $this->assertStringContainsString('Błąd parsowania JSON:', $result);
    }

    public function testGetWeatherWithValidCity()
    {
        $result = $this->server->executeTool('get_weather', ['city' => 'Warszawa']);

        $this->assertStringContainsString('=== POGODA DLA MIASTA: WARSZAWA ===', $result);
        $this->assertStringContainsString('Stan pogody:', $result);
        $this->assertStringContainsString('Temperatura:', $result);
        $this->assertStringContainsString('°C', $result);
        $this->assertStringContainsString('Wilgotność:', $result);
        $this->assertStringContainsString('%', $result);
        $this->assertStringContainsString('Prędkość wiatru:', $result);
        $this->assertStringContainsString('km/h', $result);
    }

    public function testGetWeatherWithEmptyCity()
    {
        $result = $this->server->executeTool('get_weather', ['city' => '']);

        $this->assertEquals('Błąd: Nazwa miasta jest wymagana', $result);
    }

    public function testHttpRequestWithValidUrl()
    {
        // Test with a simple public API
        $result = $this->server->executeTool('http_request', [
            'url' => 'https://httpbin.org/json',
            'method' => 'GET'
        ]);

        $this->assertStringContainsString('=== ODPOWIEDŹ HTTP ===', $result);
        $this->assertStringContainsString('URL: https://httpbin.org/json', $result);
        $this->assertStringContainsString('Metoda: GET', $result);
        $this->assertStringContainsString('Status:', $result);
        $this->assertStringContainsString('Rozmiar odpowiedzi:', $result);
    }

    public function testHttpRequestWithEmptyUrl()
    {
        $result = $this->server->executeTool('http_request', ['url' => '']);

        $this->assertEquals('Błąd: URL jest wymagany', $result);
    }

    public function testExecuteUnknownToolThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nieznane narzędzie: unknown_tool');

        $this->server->executeTool('unknown_tool', []);
    }

    public function testFileOperationSecurityPreventsPathTraversal()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Dostęp (do pliku|do katalogu) zabroniony!/');

        // Try to access files outside project directory
        $this->server->executeTool('read_file', ['path' => '../../../etc/passwd']);
    }

    public function testToolExecutionIsMonitored()
    {
        $this->monitoringMock->expects($this->once())
            ->method('recordToolExecution')
            ->with('hello', $this->anything(), true);

        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Tool executed successfully'));

        $this->server->executeTool('hello', ['name' => 'Test']);
    }

    public function testToolExecutionFailureIsMonitored()
    {
        $this->monitoringMock->expects($this->once())
            ->method('recordToolExecution')
            ->with('unknown_tool', $this->anything(), false);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Tool execution failed'));

        try {
            $this->server->executeTool('unknown_tool', []);
        } catch (\Exception $e) {
            // Expected exception
        }
    }
}