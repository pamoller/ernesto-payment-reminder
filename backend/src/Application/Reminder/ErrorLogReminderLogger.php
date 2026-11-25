<?php

declare(strict_types=1);

namespace App\Application\Reminder;

use Throwable;

final class ErrorLogReminderLogger implements ReminderLogger
{
    public function error(string $message, ?Throwable $exception = null): void
    {
        $details = $exception === null ? '' : ': ' . $exception->getMessage();
        error_log($message . $details);
    }
}
