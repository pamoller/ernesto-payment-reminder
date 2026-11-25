<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $response->getBody()->write(json_encode(
            ['status' => 'ok'],
            JSON_THROW_ON_ERROR,
        ));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
