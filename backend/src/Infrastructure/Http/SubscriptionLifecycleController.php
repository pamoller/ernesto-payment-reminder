<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Customer\CustomerService;
use App\Application\Customer\SubscriptionActionResult;
use App\Application\Customer\SubscriptionLifecycleService;
use App\Application\Reminder\ReminderClaimConflict;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class SubscriptionLifecycleController
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
        private readonly CustomerService $customers,
    ) {
    }

    public function registerPayment(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        return $this->action(
            $response,
            fn (): ?SubscriptionActionResult => $this->lifecycle->registerPayment(
                (int) $args['id'],
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            ),
            (int) $args['id'],
        );
    }

    public function reactivate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        return $this->action(
            $response,
            fn (): ?SubscriptionActionResult => $this->lifecycle->reactivate(
                (int) $args['id'],
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            ),
            (int) $args['id'],
        );
    }

    public function resend(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        return $this->action(
            $response,
            fn (): ?SubscriptionActionResult => $this->lifecycle->resendCurrent(
                (int) $args['id'],
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            ),
            (int) $args['id'],
        );
    }

    private function action(
        ResponseInterface $response,
        callable $action,
        int $customerId,
    ): ResponseInterface {
        try {
            $result = $action();
            if ($result === null) {
                return $this->json($response, ['error' => 'Customer not found'], 404);
            }
            $customer = $this->customers->find($customerId);
            return $this->json($response, [
                'customer' => $customer,
                'changed' => $result->changed,
                'email_sent' => $result->emailSent,
                'email_error' => $result->emailError,
            ]);
        } catch (ReminderClaimConflict $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 409);
        } catch (InvalidArgumentException | LogicException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (\OutOfBoundsException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 404);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return $this->json($response, ['error' => 'Subscription action failed'], 500);
        }
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
