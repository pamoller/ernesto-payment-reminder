<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Common\Clock;
use App\Application\Reminder\ReminderService;
use App\Infrastructure\Database\SettingsTestDataService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class SettingsController
{
    public function __construct(
        private readonly ReminderService $reminders,
        private readonly SettingsTestDataService $testData,
        private readonly Clock $clock,
        private readonly string $adminEmail,
    ) {
    }

    public function runCron(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        try {
            $result = $this->reminders->process($this->clock->now());
            $this->testData->prepareNextStep();
            return $this->json($response, [
                'sent' => $result->sent,
                'smtpFailures' => $result->smtpFailures,
                'resetFailures' => $result->resetFailures,
                'claimFailures' => $result->claimFailures,
                'latestSendSkipped' => $result->latestSendSkipped,
                'transitions' => $result->transitions,
                'transitionConflicts' => $result->transitionConflicts,
                'deliveryRetries' => $result->deliveryRetries,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return $this->json($response, ['error' => 'Cron could not be run'], 500);
        }
    }

    public function importTestData(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        try {
            return $this->json($response, [
                'test_data' => $this->testData->import($this->adminEmail),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            return $this->json($response, ['error' => $exception->getMessage()], 422);
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
