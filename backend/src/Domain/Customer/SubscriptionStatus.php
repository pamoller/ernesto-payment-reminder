<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use InvalidArgumentException;

final readonly class SubscriptionStatus
{
    private ?Duration $parsedDuration;

    public function __construct(
        private int $id,
        private string $name,
        private ?string $duration,
        private ?string $subject,
        private ?string $message,
    ) {
        if ($id < 1 || trim($name) === '') {
            throw new InvalidArgumentException('Subscription status id and name are required');
        }
        if (($subject === null) !== ($message === null)) {
            throw new InvalidArgumentException('Subscription status subject and message must both be configured or null');
        }

        $this->parsedDuration = $duration === null ? null : Duration::fromString($duration);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function duration(): ?string
    {
        return $this->duration;
    }

    public function parsedDuration(): ?Duration
    {
        return $this->parsedDuration;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public static function paid(): self
    {
        return new self(1, 'Bezahlt', null, 'Zahlungsbestätigung', 'Danke für das Abo!');
    }
}
