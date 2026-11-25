<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use DateTimeImmutable;
use InvalidArgumentException;

final class Subscription
{
    private ?DateTimeImmutable $reminderSent = null;

    private function __construct(
        private readonly int $durationMonths,
        private readonly DateTimeImmutable $startsAt,
        ?DateTimeImmutable $reminderSent,
        private ?int $id = null,
    ) {
        if (!in_array($durationMonths, [3, 6, 12], true)) {
            throw new InvalidArgumentException('Duration must be 3, 6 or 12 months');
        }
        $this->reminderSent = $reminderSent;
    }

    public static function create(int $durationMonths, DateTimeImmutable $startsAt): self
    {
        return new self($durationMonths, $startsAt, null);
    }

    public static function reconstitute(
        int $id,
        int $durationMonths,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $reminderSent,
    ): self {
        return new self($durationMonths, $startsAt, $reminderSent, $id);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function durationMonths(): int
    {
        return $this->durationMonths;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function reminderSent(): ?DateTimeImmutable
    {
        return $this->reminderSent;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->startsAt->modify('+' . $this->durationMonths . ' months');
    }

    public function isReminderDue(DateTimeImmutable $now): bool
    {
        return $this->reminderSent === null && $now >= $this->expiresAt();
    }

    public function markReminderSent(DateTimeImmutable $sentAt): void
    {
        $this->reminderSent = $sentAt;
    }

    public function clearReminderSent(): void
    {
        $this->reminderSent = null;
    }
}
