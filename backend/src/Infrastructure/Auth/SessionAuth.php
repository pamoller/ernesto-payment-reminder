<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class SessionAuth implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (($request->getAttribute('authUser') !== null) || !empty($_SESSION['authenticated'])) {
            return $handler->handle($request);
        }

        $response = new Response();
        $response->getBody()->write(json_encode(['error' => 'Authentication required'], JSON_THROW_ON_ERROR));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
