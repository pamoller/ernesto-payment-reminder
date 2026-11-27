<?php

declare(strict_types=1);

namespace App\Application\Reminder;

final class ReminderResult
{
    public function __construct(
        public readonly int $sent = 0,
        public readonly int $smtpFailures = 0,
        public readonly int $resetFailures = 0,
        public readonly int $claimFailures = 0,
        public readonly int $latestSendSkipped = 0,
    ) {
    }
}
