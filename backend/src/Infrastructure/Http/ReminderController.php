<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Reminder\ReminderClaimConflict;
use App\Application\Reminder\ReminderDeliveryFailed;
use App\Application\Reminder\ReminderAuthorizationRequired;
use App\Application\Reminder\ReminderService;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ReminderController
{
    public function __construct(private readonly ReminderService $service)
    {
    }

    public function send(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        try {
            $input = $request->getParsedBody();
            $authoriseRepeatedReminder = is_array($input)
                && (($input['authorise_repeated_reminder'] ?? false) === true);
            $sentAt = $this->service->sendManually(
                (int) $args['id'],
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                $authoriseRepeatedReminder,
            );
            if ($sentAt === null) {
                return $this->json($response, [
                    'sent' => false,
                    'reset' => true,
                    'reminder_sent' => null,
                ]);
            }
            return $this->json($response, [
                'sent' => true,
                'reminder_sent' => $sentAt->format('Y-m-d\TH:i:s.uP'),
            ]);
        } catch (OutOfBoundsException) {
            return $this->json($response, ['error' => 'Customer not found'], 404);
        } catch (ReminderClaimConflict $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 409);
        } catch (ReminderAuthorizationRequired $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 409);
        } catch (ReminderDeliveryFailed $exception) {
            error_log($exception->getMessage() . ': ' . $exception->getPrevious()?->getMessage());
            return $this->json($response, ['error' => $exception->getMessage()], 502);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return $this->json($response, ['error' => 'Reminder could not be sent'], 500);
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
