<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Customer\CustomerService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class CustomerController
{
    public function __construct(private readonly CustomerService $service)
    {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($query['per_page'] ?? 25, FILTER_VALIDATE_INT);
        $sort = is_string($query['sort'] ?? null) ? $query['sort'] : 'expires_at';
        $direction = is_string($query['direction'] ?? null) ? $query['direction'] : 'asc';
        if ($page === false || $page < 1 || $perPage === false || $perPage !== 25) {
            return $this->json($response, ['error' => 'Ungültige Paginierungsparameter'], 422);
        }
        $result = $this->service->paginate((int) $page, (int) $perPage, $sort, $direction);
        return $this->json($response, $result);
    }

    public function listSubscriptionTypes(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        return $this->json($response, ['subscription_types' => $this->service->subscriptionTypes()]);
    }

    public function listSubscriptionStatuses(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        return $this->json($response, ['subscription_statuses' => $this->service->subscriptionStatuses()]);
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $customer = $this->service->find((int) $args['id']);
        return $customer === null
            ? $this->json($response, ['error' => 'Customer not found'], 404)
            : $this->json($response, ['customer' => $customer]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $input = $request->getParsedBody();
            $customer = $this->service->create(is_array($input) ? $input : []);
            return $this->json($response, ['customer' => $customer], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return $this->serverError($response, $exception);
        }
    }

    public function update(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        try {
            $input = $request->getParsedBody();
            $customer = $this->service->update((int) $args['id'], is_array($input) ? $input : []);
            return $customer === null
                ? $this->json($response, ['error' => 'Customer not found'], 404)
                : $this->json($response, ['customer' => $customer]);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return $this->serverError($response, $exception);
        }
    }

    public function renew(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        try {
            $customer = $this->service->renew(
                (int) $args['id'],
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
            return $customer === null
                ? $this->json($response, ['error' => 'Customer not found'], 404)
                : $this->json($response, ['customer' => $customer]);
        } catch (Throwable $exception) {
            return $this->serverError($response, $exception);
        }
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->service->delete((int) $args['id'])) {
            return $this->json($response, ['error' => 'Customer not found'], 404);
        }
        return $response->withStatus(204);
    }

    private function serverError(ResponseInterface $response, Throwable $exception): ResponseInterface
    {
        error_log($exception->getMessage());
        return $this->json($response, ['error' => 'Internal server error'], 500);
    }

    /** @param array<string, mixed> $payload */
    private function json(
        ResponseInterface $response,
        array $payload,
        int $status = 200,
    ): ResponseInterface {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
