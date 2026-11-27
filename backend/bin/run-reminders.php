<?php

declare(strict_types=1);

use App\Application\Reminder\ErrorLogReminderLogger;
use App\Application\Reminder\ReminderService;
use App\Infrastructure\Database\PdoCustomerRepository;
use App\Infrastructure\Database\PdoFactory;
use App\Infrastructure\Reminder\PhpmailerReminderMailer;
use App\Infrastructure\Database\PdoSubscriptionStatusRepository;

require __DIR__ . '/../vendor/autoload.php';

PdoFactory::loadEnv();
$latestSendValue = getenv('MESSAGE_LATEST_SEND');
if ($latestSendValue === false
    || filter_var($latestSendValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false
) {
    fwrite(STDERR, "MESSAGE_LATEST_SEND must be a non-negative integer\n");
    exit(1);
}

$pdo = PdoFactory::create();
$repository = new PdoCustomerRepository($pdo);
$logger = new ErrorLogReminderLogger();

$mailer = new PhpmailerReminderMailer(
    getenv('SMTP_HOST') ?: '',
    (int) (getenv('SMTP_PORT') ?: 587),
    getenv('SMTP_USER') ?: '',
    getenv('SMTP_PASSWORD') ?: '',
    getenv('SMTP_FROM') ?: '',
    getenv('SMTP_FROM_NAME') ?: 'Ernesto',
    getenv('ADMIN_EMAIL') ?: '',
    getenv('SMTP_SECURE') ?: 'tls',
);

$service = new ReminderService(
    $repository,
    $mailer,
    $logger,
    (int) $latestSendValue,
    new PdoSubscriptionStatusRepository($pdo),
);

try {
    $result = $service->process(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    echo json_encode([
        'sent' => $result->sent,
        'smtpFailures' => $result->smtpFailures,
        'resetFailures' => $result->resetFailures,
        'claimFailures' => $result->claimFailures,
        'latestSendSkipped' => $result->latestSendSkipped,
        'transitions' => $result->transitions,
        'transitionConflicts' => $result->transitionConflicts,
        'deliveryRetries' => $result->deliveryRetries,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

    exit(($result->smtpFailures > 0 || $result->resetFailures > 0 || $result->claimFailures > 0) ? 1 : 0);
} catch (Throwable $exception) {
    $logger->error('Reminder run failed', $exception);
    fwrite(STDERR, 'Reminder run failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
