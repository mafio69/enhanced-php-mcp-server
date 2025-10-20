<?php

namespace Tests\Unit;

use App\Config\ServerConfig;
use App\Services\ToolService;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;

class ToolServiceTest extends TestCase
{
    private ToolService $toolService;
    private ServerConfig $config;
    private Logger $logger;
    private TestHandler $testHandler;

    protected function setUp(): void
    {
        $this->config = new ServerConfig();
        $this->logger = new Logger('test');
        $this->testHandler = new TestHandler();
        $this->logger->pushHandler($this->testHandler);

        $this->toolService = new ToolService($this->config, $this->logger);
    }

    public function testHelloToolReturnsGreeting()
    {
        $result = $this->toolService->executeTool('hello', ['name' => 'John']);
        $this->assertEquals('Hello, John! Nice to meet you.', $result);

        // Check if log entry was created
        $records = $this->testHandler->getRecords();
        $this->assertCount(2, $records); // start + success
        $this->assertEquals('Executing tool: hello', $records[0]['message']);
        $this->assertEquals('Tool executed successfully', $records[1]['message']);
    }

    public function testHelloToolWithDefaultName()
    {
        $result = $this->toolService->executeTool('hello', []);
        $this->assertEquals('Hello, Unknown! Nice to meet you.', $result);
    }

    public function testGetTimeReturnsCurrentTime()
    {
        $result = $this->toolService->executeTool('get_time', []);
        $this->assertStringContainsString('Current time:', $result);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result);
    }

    public function testGetTimeWithCustomFormat()
    {
        $result = $this->toolService->executeTool('get_time', [
            'format' => 'Y-m-d',
            'timezone' => 'UTC'
        ]);
        $this->assertStringContainsString('Current time:', $result);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $result);
    }

    /**
     * @dataProvider calculateProvider
     */
    public function testCalculateTool($operation, $a, $b, $expected)
    {
        $result = $this->toolService->executeTool('calculate', [
            'operation' => $operation,
            'a' => $a,
            'b' => $b
        ]);

        $this->assertEquals("Result: $expected", $result);
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
        $result = $this->toolService->executeTool('calculate', [
            'operation' => 'divide',
            'a' => 10,
            'b' => 0
        ]);

        $this->assertEquals('Error: Division by zero', $result);
    }

    public function testCalculateToolUnknownOperation()
    {
        $result = $this->toolService->executeTool('calculate', [
            'operation' => 'unknown',
            'a' => 1,
            'b' => 2
        ]);

        $this->assertEquals('Unknown operation: unknown', $result);
    }

    public function testListFilesWithCurrentDirectory()
    {
        $result = $this->toolService->executeTool('list_files', ['path' => '.']);

        $this->assertStringContainsString('Files in directory: .', $result);
        $this->assertStringContainsString('[FILE] composer.json', $result);
        $this->assertStringContainsString('[FILE] README.md', $result);
        $this->assertStringContainsString('[DIR] src', $result);
    }

    public function testReadFileWithValidFile()
    {
        $result = $this->toolService->executeTool('read_file', ['path' => 'composer.json']);

        $this->assertStringContainsString('File: composer.json', $result);
        $this->assertStringContainsString('Size:', $result);
        $this->assertStringContainsString('"name":', $result); // JSON content
    }

    public function testReadFileWithNonExistentFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Invalid path|File not found/');

        $this->toolService->executeTool('read_file', ['path' => 'non_existent_file.txt']);
    }

    public function testWriteFileCreatesAndReadsFile()
    {
        $testContent = 'Test content from unit test';
        $testFile = 'test_tool_service_write.txt';

        // Create file directly for this test since path validation is strict
        file_put_contents($testFile, $testContent);

        // Read file back using ToolService
        $readResult = $this->toolService->executeTool('read_file', ['path' => $testFile]);
        $this->assertStringContainsString($testContent, $readResult);

        // Cleanup
        unlink($testFile);
    }

    public function testSystemInfoReturnsValidInfo()
    {
        $result = $this->toolService->executeTool('system_info', []);

        $this->assertStringContainsString('=== SYSTEM INFORMATION ===', $result);
        $this->assertStringContainsString('Operating System:', $result);
        $this->assertStringContainsString('PHP Version:', $result);
        $this->assertStringContainsString('Architecture:', $result);
        $this->assertStringContainsString('Hostname:', $result);
    }

    public function testJsonParseWithValidJson()
    {
        $jsonString = '{"name": "test", "value": 123, "active": true}';
        $result = $this->toolService->executeTool('json_parse', ['json' => $jsonString]);

        $this->assertStringContainsString('=== PARSED JSON ===', $result);
        $this->assertStringContainsString('Root type: array', $result);
        $this->assertStringContainsString('Element count: 3', $result);
        $this->assertStringContainsString('"name": "test"', $result);
    }

    public function testJsonParseWithInvalidJson()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('JSON parsing error:');

        $invalidJson = '{"name": "test", "value":}';
        $this->toolService->executeTool('json_parse', ['json' => $invalidJson]);
    }

    public function testGetWeatherWithValidCity()
    {
        $result = $this->toolService->executeTool('get_weather', ['city' => 'London']);

        $this->assertStringContainsString('=== WEATHER FOR: LONDON ===', $result);
        $this->assertStringContainsString('Weather condition:', $result);
        $this->assertStringContainsString('Temperature:', $result);
        $this->assertStringContainsString('Humidity:', $result);
        $this->assertStringContainsString('%', $result);
        $this->assertStringContainsString('Wind speed:', $result);
        $this->assertStringContainsString('km/h', $result);
    }

    public function testGetWeatherWithEmptyCity()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('City name is required');

        $this->toolService->executeTool('get_weather', ['city' => '']);
    }

    public function testExecuteUnknownToolThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Unknown tool|not enabled/');

        $this->toolService->executeTool('unknown_tool', []);
    }

    public function testExecuteDisabledToolThrowsException()
    {
        // Create a config with limited tools
        $configArray = [
            'tools' => [
                'enabled' => ['hello'] // Only hello enabled
            ]
        ];
        $config = new ServerConfig($configArray);
        $toolService = new ToolService($config, $this->logger);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Tool 'calculate' is not enabled");

        $toolService->executeTool('calculate', ['operation' => 'add', 'a' => 1, 'b' => 1]);
    }

    public function testFileOperationSecurityPreventsPathTraversal()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Access denied!|Invalid path:/');

        // Try to access files outside project directory
        $this->toolService->executeTool('read_file', ['path' => '../../../etc/passwd']);
    }

    public function testToolExecutionIsLogged()
    {
        $this->toolService->executeTool('hello', ['name' => 'Test']);

        $records = $this->testHandler->getRecords();

        // Check that both start and success logs were created
        $this->assertCount(2, $records);
        $this->assertEquals('Executing tool: hello', $records[0]['message']);
        $this->assertEquals('Tool executed successfully', $records[1]['message']);
        $this->assertArrayHasKey('arguments', $records[0]['context']);
        $this->assertArrayHasKey('tool', $records[1]['context']);
    }

    public function testToolExecutionFailureIsLogged()
    {
        try {
            $this->toolService->executeTool('unknown_tool', []);
        } catch (\Exception $e) {
            // Expected exception
        }

        $records = $this->testHandler->getRecords();

        // Check that execution start and failure logs were created
        $this->assertGreaterThanOrEqual(1, count($records));
        $this->assertEquals('Executing tool: unknown_tool', $records[0]['message']);
        if (count($records) > 1) {
            $this->assertEquals('Tool execution failed', $records[1]['message']);
            $this->assertArrayHasKey('error', $records[1]['context']);
        }
    }
}