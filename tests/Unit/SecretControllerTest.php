<?php

namespace Tests\Unit;

use App\Config\ServerConfig;
use App\Controllers\SecretController;
use App\Services\SecretManagerService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\Psr7\Response as SlimResponse;

class SecretControllerTest extends TestCase
{
    private SecretController $controller;
    private SecretManagerService $secretManagerMock;
    private LoggerInterface $loggerMock;
    private ServerConfig $config;

    public function testListSecretsReturnsKeys(): void
    {
        $this->secretManagerMock->method('listSecrets')
            ->willReturn(['key.one', 'key.two']);

        $response = $this->controller->listSecrets(
            $this->createJsonRequest('GET'),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertCount(2, $body['data']);
        $this->assertSame('key.one', $body['data'][0]);
    }

    private function createJsonRequest(string $method, array $body = [], ?string $key = null): Request
    {
        $request = $this->createMock(Request::class);

        $request->method('getMethod')->willReturn($method);

        if ($key !== null) {
            $request->method('getAttribute')->with('key')->willReturn($key);
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, json_encode($body));
        rewind($stream);

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
        $body = (string)$response->getBody();

        return json_decode($body, true) ?? [];
    }

    public function testListSecretsHandlesError(): void
    {
        $this->secretManagerMock->method('listSecrets')
            ->willThrowException(new RuntimeException('Storage error'));

        $response = $this->controller->listSecrets(
            $this->createJsonRequest('GET'),
            $this->createResponse()
        );

        $this->assertSame(500, $response->getStatusCode());
        $body = $this->getResponseBody($response);
        $this->assertFalse($body['success']);
    }

    public function testStoreSecretSuccess(): void
    {
        $this->secretManagerMock->expects($this->once())
            ->method('storeSecret')
            ->with('test.key', 'test-value');

        $response = $this->controller->storeSecret(
            $this->createJsonRequest('POST', ['key' => 'test.key', 'value' => 'test-value']),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
    }

    public function testStoreSecretMissingFields(): void
    {
        $response = $this->controller->storeSecret(
            $this->createJsonRequest('POST', ['key' => 'test.key']),
            $this->createResponse()
        );

        $this->assertSame(400, $response->getStatusCode());
        $body = $this->getResponseBody($response);
        $this->assertFalse($body['success']);
    }

    public function testStoreSecretEmptyFields(): void
    {
        $response = $this->controller->storeSecret(
            $this->createJsonRequest('POST', ['key' => '  ', 'value' => '  ']),
            $this->createResponse()
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testStoreSecretHandlesServiceError(): void
    {
        $this->secretManagerMock->method('storeSecret')
            ->willThrowException(new RuntimeException('Write failed'));

        $response = $this->controller->storeSecret(
            $this->createJsonRequest('POST', ['key' => 'test.key', 'value' => 'test-value']),
            $this->createResponse()
        );

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testGetSecretFound(): void
    {
        $this->secretManagerMock->method('getSecret')
            ->with('my.key')
            ->willReturn('secret-value');

        $response = $this->controller->getSecret(
            $this->createJsonRequest('GET', [], 'my.key'),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame('my.key', $body['data']['key']);
        $this->assertSame('secret-value', $body['data']['value']);
    }

    public function testGetSecretNotFound(): void
    {
        $this->secretManagerMock->method('getSecret')
            ->with('missing.key')
            ->willReturn(null);

        $response = $this->controller->getSecret(
            $this->createJsonRequest('GET', [], 'missing.key'),
            $this->createResponse()
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testGetSecretMissingKey(): void
    {
        $response = $this->controller->getSecret(
            $this->createJsonRequest('GET', [], ''),
            $this->createResponse()
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteSecretSuccess(): void
    {
        $this->secretManagerMock->method('secretExists')->with('my.key')->willReturn(true);
        $this->secretManagerMock->method('deleteSecret')->with('my.key')->willReturn(true);

        $response = $this->controller->deleteSecret(
            $this->createJsonRequest('DELETE', [], 'my.key'),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
    }

    public function testDeleteSecretNotFound(): void
    {
        $this->secretManagerMock->method('secretExists')->with('my.key')->willReturn(false);

        $response = $this->controller->deleteSecret(
            $this->createJsonRequest('DELETE', [], 'my.key'),
            $this->createResponse()
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteSecretMissingKey(): void
    {
        $response = $this->controller->deleteSecret(
            $this->createJsonRequest('DELETE', [], ''),
            $this->createResponse()
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCheckSecretExists(): void
    {
        $this->secretManagerMock->method('secretExists')->with('my.key')->willReturn(true);

        $response = $this->controller->checkSecret(
            $this->createJsonRequest('GET', [], 'my.key'),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['data']['exists']);
    }

    public function testCheckSecretNotExists(): void
    {
        $this->secretManagerMock->method('secretExists')->with('my.key')->willReturn(false);

        $response = $this->controller->checkSecret(
            $this->createJsonRequest('GET', [], 'my.key'),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertFalse($body['data']['exists']);
    }

    public function testEncryptValueSuccess(): void
    {
        $this->secretManagerMock->method('encrypt')
            ->with('my-value')
            ->willReturn('encrypted-string');

        $response = $this->controller->encryptValue(
            $this->createJsonRequest('POST', ['value' => 'my-value']),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('encrypted-string', $body['data']['encrypted']);
    }

    public function testEncryptValueMissingField(): void
    {
        $response = $this->controller->encryptValue(
            $this->createJsonRequest('POST', []),
            $this->createResponse()
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDecryptValueSuccess(): void
    {
        $this->secretManagerMock->method('decrypt')
            ->with('encrypted-string')
            ->willReturn('original-value');

        $response = $this->controller->decryptValue(
            $this->createJsonRequest('POST', ['encrypted' => 'encrypted-string']),
            $this->createResponse()
        );

        $body = $this->getResponseBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('original-value', $body['data']['decrypted']);
    }

    public function testDecryptValueMissingField(): void
    {
        $response = $this->controller->decryptValue(
            $this->createJsonRequest('POST', []),
            $this->createResponse()
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        $this->config = new ServerConfig();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->secretManagerMock = $this->createMock(SecretManagerService::class);

        $this->controller = new SecretController(
            $this->config,
            $this->loggerMock,
            $this->secretManagerMock
        );
    }
}
