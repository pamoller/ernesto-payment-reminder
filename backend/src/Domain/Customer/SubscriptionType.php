<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use InvalidArgumentException;
use UnexpectedValueException;

final readonly class SubscriptionType
{
    private Duration $parsedDuration;

    public function __construct(
        public int $id,
        public string $name,
        public string $duration,
        public string $reminder,
    ) {
        if ($id < 1 || trim($name) === '' || trim($reminder) === '') {
            throw new InvalidArgumentException('Subscription type fields are required');
        }
        $this->parsedDuration = Duration::fromString($duration);
    }

    public function parsedDuration(): Duration
    {
        return $this->parsedDuration;
    }

    public function durationMonths(): ?int
    {
        if (preg_match('/^([0-9]+)\s+MONTHS?$/i', $this->duration, $matches) !== 1) {
            return null;
        }
        return (int) $matches[1];
    }

    public static function durationMonthsFrom(string $duration): int
    {
        if (preg_match('/^([0-9]+)\s+MONTHS?$/i', $duration, $matches) !== 1) {
            throw new UnexpectedValueException('Subscription type duration is not expressed in months');
        }
        return (int) $matches[1];
    }
}
