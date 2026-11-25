<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\HealthController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class HealthControllerTest extends TestCase
{
    public function testHealthReturnsOkJson(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = (new ResponseFactory())->createResponse();

        $result = (new HealthController())($request, $response);

        self::assertSame(200, $result->getStatusCode());
        self::assertSame('application/json', $result->getHeaderLine('Content-Type'));
        self::assertSame('{"status":"ok"}', (string) $result->getBody());
    }
}
