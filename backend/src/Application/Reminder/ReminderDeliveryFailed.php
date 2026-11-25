<?php

declare(strict_types=1);

namespace App\Application\Reminder;

use RuntimeException;
use Throwable;

final class ReminderDeliveryFailed extends RuntimeException
{
    public function __construct(string $message, Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
