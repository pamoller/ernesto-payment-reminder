<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use InvalidArgumentException;
use UnexpectedValueException;

final readonly class SubscriptionType
{
    public function __construct(
        public int $id,
        public string $name,
        public string $duration,
        public string $reminder,
    ) {
        if ($id < 1 || trim($name) === '' || trim($reminder) === '') {
            throw new InvalidArgumentException('Subscription type fields are required');
        }
        self::durationMonthsFrom($duration);
    }

    public function durationMonths(): int
    {
        return self::durationMonthsFrom($this->duration);
    }

    public static function durationMonthsFrom(string $duration): int
    {
        if (preg_match('/^([1-9][0-9]*) MONTHS$/', $duration, $matches) !== 1) {
            throw new UnexpectedValueException('Subscription type duration has an invalid format');
        }
        return (int) $matches[1];
    }
}
