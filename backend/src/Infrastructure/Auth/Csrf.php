<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class Csrf implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $handler->handle($request);
        }

        $submitted = $request->getHeaderLine('X-CSRF-Token');
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sessionToken === '' || $submitted === '' || !hash_equals($sessionToken, $submitted)) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid CSRF token'], JSON_THROW_ON_ERROR));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(419);
        }

        return $handler->handle($request);
    }
}
