<?php

declare(strict_types=1);

namespace App\Application\Reminder;

use Throwable;

interface ReminderLogger
{
    public function error(string $message, ?Throwable $exception = null): void;
}
