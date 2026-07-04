<?php

namespace Tests\Unit;

use App\Config\ServerConfig;
use App\Controllers\ServerController;
use App\Services\ServerService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response as SlimResponse;

class ServerControllerTest extends TestCase
{
    private ServerController $controller;
    private ServerService $serverServiceMock;
    private LoggerInterface $loggerMock;
    private ServerConfig $config;

    protected function setUp(): void
    {
        $this->config = new ServerConfig();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->serverServiceMock = $this->createMock(ServerService::class);

        $this->controller = new ServerController(
            $this->serverServiceMock,
            $this->config,
            $this->loggerMock
        );
    }

    private function createJsonRequest(string $method, array $body = []): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn($method);

        $bodyMock = $this->createMock(StreamInterface::class);
        $bodyMock->method('getContents')->willReturn(json_encode($body));
        $bodyMock->method('__toString')->willReturn(json_encode($body));

        $request->method('getBody')->willReturn($bodyMock);
        $request->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');

        return $request;
    }

    private function createResponse(): Response
    {
        return new SlimResponse();
    }

    private function getResponseBody(Response $response): array
    {
        $body = (string) $response->getBody();
        return json_decode($body, true) ?? [];
    }

    public function testListServersReturnsServers(): void
    {
        $mockServers = [
            'brave-search' => [
                'command' => 'npx',
                'args' => ['brave-search-mcp-server'],
            ]
        ];

        $this->serverServiceMock->method('getServers')
            ->willReturn($mockServers);

        $response = $this->controller->listServers(
            $this->createJsonRequest('GET'),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame($mockServers, $body['data']);
    }

    public function testAddServerSuccess(): void
    {
        $serverPayload = [
            'name' => 'test-server',
            'json_config' => json_encode([
                'command' => 'echo',
                'args' => ['hi'],
            ]),
        ];

        $this->serverServiceMock->expects($this->once())
            ->method('addServer')
            ->with([
                'name' => 'test-server',
                'config' => [
                    'command' => 'echo',
                    'args' => ['hi'],
                ]
            ])
            ->willReturn([
                'name' => 'test-server',
                'config' => [
                    'command' => 'echo',
                    'args' => ['hi'],
                ]
            ]);

        $response = $this->controller->addServer(
            $this->createJsonRequest('POST', $serverPayload),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame('test-server', $body['data']['name']);
    }

    public function testAddServerInvalidInput(): void
    {
        $serverPayload = [
            'name' => '',
            'json_config' => 'invalid-json',
        ];

        $response = $this->controller->addServer(
            $this->createJsonRequest('POST', $serverPayload),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('name', $body['details']);
        $this->assertArrayHasKey('json_config', $body['details']);
    }

    public function testDeleteServerSuccess(): void
    {
        $this->serverServiceMock->expects($this->once())
            ->method('deleteServer')
            ->with('my-server');

        $response = $this->controller->deleteServer(
            $this->createJsonRequest('DELETE'),
            $this->createResponse(),
            ['name' => 'my-server']
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame('Server deleted successfully', $body['data']['message']);
    }

    public function testDeleteServerMissingName(): void
    {
        $response = $this->controller->deleteServer(
            $this->createJsonRequest('DELETE'),
            $this->createResponse(),
            []
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($body['success']);
    }
}
