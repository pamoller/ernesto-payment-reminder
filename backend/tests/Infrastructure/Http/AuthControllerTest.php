<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\AuthController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        putenv('AUTH_USERNAME=admin');
        putenv('AUTH_PASSWORD_HASH=' . password_hash('test-password', PASSWORD_ARGON2ID));
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        putenv('AUTH_USERNAME');
        putenv('AUTH_PASSWORD_HASH');
    }

    public function testSessionReturnsCsrfToken(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/auth/session');
        $response = (new ResponseFactory())->createResponse();

        $result = (new AuthController())->session($request, $response);
        $body = json_decode((string) $result->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $result->getStatusCode());
        self::assertFalse($body['authenticated']);
        self::assertNotSame('', $body['csrf_token']);
    }

    public function testLoginRejectsInvalidPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/auth/login')
            ->withParsedBody([
                'username' => 'admin',
                'password' => 'wrong-password',
            ]);
        $response = (new ResponseFactory())->createResponse();
        $controller = new AuthController();

        $sessionResult = $controller->session($request, $response);
        $sessionBody = json_decode((string) $sessionResult->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $request = $request->withHeader('X-CSRF-Token', $sessionBody['csrf_token']);

        $result = $controller->login(
            $request,
            (new ResponseFactory())->createResponse(),
        );

        self::assertSame(401, $result->getStatusCode());
    }

    public function testLoginAcceptsValidArgon2idPassword(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/auth/login')
            ->withParsedBody([
                'username' => 'admin',
                'password' => 'test-password',
            ]);
        $response = (new ResponseFactory())->createResponse();
        $controller = new AuthController();

        $sessionResult = $controller->session($request, $response);
        $sessionBody = json_decode((string) $sessionResult->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $request = $request->withHeader('X-CSRF-Token', $sessionBody['csrf_token']);

        $result = $controller->login(
            $request,
            (new ResponseFactory())->createResponse(),
        );
        $body = json_decode((string) $result->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $result->getStatusCode());
        self::assertTrue($body['authenticated']);
    }
}
