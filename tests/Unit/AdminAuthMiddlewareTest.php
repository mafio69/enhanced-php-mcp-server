<?php

namespace Tests\Unit;

use App\Middleware\AdminAuthMiddleware;
use App\Services\AdminAuthService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response as SlimResponse;

class AdminAuthMiddlewareTest extends TestCase
{
    private AdminAuthMiddleware $middleware;
    private AdminAuthService $authServiceMock;
    private LoggerInterface $loggerMock;

    protected function setUp(): void
    {
        $this->authServiceMock = $this->createMock(AdminAuthService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->middleware = new AdminAuthMiddleware($this->authServiceMock, $this->loggerMock);
    }

    private function createRequestMock(string $path, ?string $bearerToken, ?string $cookieToken = null): Request
    {
        $request = $this->createMock(Request::class);

        $request->method('getHeaderLine')->willReturnMap([
            ['Authorization', $bearerToken !== null ? 'Bearer ' . $bearerToken : ''],
        ]);
        $request->method('getCookieParams')->willReturn(
            $cookieToken !== null ? ['admin_session' => $cookieToken] : []
        );

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $request->method('getUri')->willReturn($uri);

        $request->method('withAttribute')->willReturnCallback(
            function (string $name, $value) use ($request) {
                $newRequest = $this->createMock(Request::class);
                $newRequest->method('getAttribute')->willReturnMap([
                    ['admin_user', null, $name === 'admin_user' ? $value : null],
                    ['session_id', null, $name === 'session_id' ? $value : null],
                ]);
                $newRequest->method('getHeaderLine')->willReturnMap(
                    $request->getHeaderLine('Authorization') !== ''
                        ? [['Authorization', 'Bearer test-token']]
                        : [['Authorization', '']]
                );

                return $newRequest;
            }
        );

        return $request;
    }

    private function createChainedRequestMock(string $path, ?string $bearerToken, ?string $cookieToken = null): Request
    {
        $request = $this->createMock(Request::class);

        $request->method('getHeaderLine')->willReturnMap([
            ['Authorization', $bearerToken !== null ? 'Bearer ' . $bearerToken : ''],
        ]);
        $request->method('getCookieParams')->willReturn(
            $cookieToken !== null ? ['admin_session' => $cookieToken] : []
        );

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $request->method('getUri')->willReturn($uri);

        $request->method('withAttribute')->willReturnCallback(
            function (string $name, $value) use ($request) {
                $newRequest = $this->createMock(Request::class);
                $newRequest->method('getAttribute')->willReturnCallback(
                    function (string $attrName) use ($name, $value) {
                        static $attrs = [];
                        $attrs[$name] = $value;

                        return $attrs[$attrName] ?? null;
                    }
                );
                $newRequest->method('getHeaderLine')->willReturnMap(
                    $request->getHeaderLine('Authorization') !== ''
                        ? [['Authorization', 'Bearer test-token']]
                        : [['Authorization', '']]
                );
                $newRequest->method('getUri')->willReturn($request->getUri());
                $newRequest->method('getCookieParams')->willReturn($request->getCookieParams());

                $newRequest->method('withAttribute')->willReturnCallback(
                    function (string $name2, $value2) use ($newRequest, $name, $value) {
                        $finalRequest = $this->createMock(Request::class);
                        $finalRequest->method('getAttribute')->willReturnCallback(
                            function (string $attrName) use ($name, $value, $name2, $value2) {
                                $attrs = [
                                    $name => $value,
                                    $name2 => $value2,
                                ];

                                return $attrs[$attrName] ?? null;
                            }
                        );
                        $finalRequest->method('getHeaderLine')->willReturnMap($newRequest->getHeaderLine('Authorization') !== ''
                            ? [['Authorization', 'Bearer test-token']]
                            : [['Authorization', '']]);
                        $finalRequest->method('getUri')->willReturn($newRequest->getUri());
                        $finalRequest->method('getCookieParams')->willReturn($newRequest->getCookieParams());

                        return $finalRequest;
                    }
                );

                return $newRequest;
            }
        );

        return $request;
    }

    private function createHandler(): RequestHandler
    {
        $handler = $this->createMock(RequestHandler::class);
        $handler->method('handle')->willReturn(new SlimResponse());

        return $handler;
    }

    public function testApiRequestWithoutAuthReturns401(): void
    {
        $request = $this->createRequestMock('/admin/api/secrets', null);
        $handler = $this->createHandler();

        $response = ($this->middleware)($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
    }

    public function testWebRequestWithoutAuthRedirectsToLogin(): void
    {
        $request = $this->createRequestMock('/admin/dashboard', null);
        $handler = $this->createHandler();

        $response = ($this->middleware)($request, $handler);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/admin/login', $response->getHeaderLine('Location'));
    }

    public function testApiRequestWithInvalidSessionReturns401(): void
    {
        $this->authServiceMock->method('validateSession')
            ->with('invalid-token')
            ->willReturn(null);

        $request = $this->createRequestMock('/admin/api/secrets', 'invalid-token');
        $handler = $this->createHandler();

        $response = ($this->middleware)($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testRequestWithValidSessionPassesThrough(): void
    {
        $sessionData = [
            'username' => 'admin',
            'created_at' => time(),
        ];

        $this->authServiceMock->method('validateSession')
            ->with('valid-token')
            ->willReturn($sessionData);

        $request = $this->createChainedRequestMock('/admin/api/secrets', 'valid-token');
        $handler = $this->createHandler();

        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Request $req) {
                $user = $req->getAttribute('admin_user');
                $sessionId = $req->getAttribute('session_id');

                return $user !== null && $user['username'] === 'admin'
                    && $sessionId === 'valid-token';
            }));

        ($this->middleware)($request, $handler);
    }

    public function testCookieAuthWorks(): void
    {
        $sessionData = [
            'username' => 'admin',
            'created_at' => time(),
        ];

        $this->authServiceMock->method('validateSession')
            ->with('cookie-token')
            ->willReturn($sessionData);

        $request = $this->createChainedRequestMock('/admin/api/secrets', null, 'cookie-token');
        $handler = $this->createHandler();

        $handler->expects($this->once())->method('handle');
        ($this->middleware)($request, $handler);
    }

    public function testBearerTokenTakesPrecedenceOverCookie(): void
    {
        $sessionData = [
            'username' => 'admin',
            'created_at' => time(),
        ];

        $this->authServiceMock->method('validateSession')
            ->with('bearer-token')
            ->willReturn($sessionData);

        $request = $this->createChainedRequestMock('/admin/api/secrets', 'bearer-token', 'cookie-token');
        $handler = $this->createHandler();

        $handler->expects($this->once())->method('handle');
        ($this->middleware)($request, $handler);
    }

    public function testBearerNullTokenFallsBackToCookie(): void
    {
        $sessionData = [
            'username' => 'admin',
            'created_at' => time(),
        ];

        $this->authServiceMock->method('validateSession')
            ->with('cookie-token')
            ->willReturn($sessionData);

        $request = $this->createChainedRequestMock('/admin/api/secrets', 'null', 'cookie-token');
        $handler = $this->createHandler();

        $handler->expects($this->once())->method('handle');
        ($this->middleware)($request, $handler);
    }

    public function testBearerEmptyTokenFallsBackToCookie(): void
    {
        $sessionData = [
            'username' => 'admin',
            'created_at' => time(),
        ];

        $this->authServiceMock->method('validateSession')
            ->with('cookie-token')
            ->willReturn($sessionData);

        $request = $this->createChainedRequestMock('/admin/api/secrets', '', 'cookie-token');
        $handler = $this->createHandler();

        $handler->expects($this->once())->method('handle');
        ($this->middleware)($request, $handler);
    }
}
