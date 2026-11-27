<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Customer\CustomerService;
use App\Application\Customer\SubscriptionLifecycleService;
use App\Application\Reminder\ReminderClaimConflict;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use App\Application\Common\Clock;

final class SubscriptionLifecycleController
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
        private readonly CustomerService $customers,
        private readonly Clock $clock,
    ) {
    }

    public function resend(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        try {
            $customerId = (int) $args['id'];
            $result = $this->lifecycle->resendCurrent(
                $customerId,
                $this->clock->now(),
            );
            if ($result === null) {
                return $this->json($response, ['error' => 'Customer not found'], 404);
            }
            return $this->json($response, [
                'customer' => $this->customers->find($customerId),
                'changed' => $result->changed,
                'email_sent' => $result->emailSent,
                'email_error' => $result->emailError,
            ]);
        } catch (ReminderClaimConflict $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 409);
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
