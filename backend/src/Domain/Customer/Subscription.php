<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

final class Subscription
{
    private ?DateTimeImmutable $reminderSent = null;

    private function __construct(
        private readonly int $subscriptionTypeId,
        private readonly string $duration,
        private DateTimeImmutable $startsAt,
        ?DateTimeImmutable $reminderSent,
        private SubscriptionStatus $status,
        private DateTimeImmutable $statusChangedAt,
        private ?int $id = null,
    ) {
        if ($subscriptionTypeId < 1) {
            throw new InvalidArgumentException('Subscription type is required');
        }
        Duration::fromString($duration);
        $this->reminderSent = $reminderSent;
    }

    public static function create(
        int $subscriptionTypeId,
        int|string $duration,
        DateTimeImmutable $startsAt,
        ?SubscriptionStatus $status = null,
        ?DateTimeImmutable $statusChangedAt = null,
    ): self
    {
        return new self(
            $subscriptionTypeId,
            is_int($duration) ? $duration . ' MONTHS' : $duration,
            $startsAt,
            null,
            $status ?? SubscriptionStatus::paid(),
            $statusChangedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    public static function reconstitute(
        int $id,
        int $subscriptionTypeId,
        int|string $duration,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $reminderSent,
        ?SubscriptionStatus $status = null,
        ?DateTimeImmutable $statusChangedAt = null,
    ): self {
        return new self(
            $subscriptionTypeId,
            is_int($duration) ? $duration . ' MONTHS' : $duration,
            $startsAt,
            $reminderSent,
            $status ?? SubscriptionStatus::paid(),
            $statusChangedAt ?? $startsAt,
            $id,
        );
    }

    public function subscriptionTypeId(): int
    {
        return $this->subscriptionTypeId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function duration(): string
    {
        return $this->duration;
    }

    public function durationMonths(): ?int
    {
        if (preg_match('/^([0-9]+)\s+MONTHS?$/i', $this->duration, $matches) !== 1) {
            return null;
        }
        return (int) $matches[1];
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function statusChangedAt(): DateTimeImmutable
    {
        return $this->statusChangedAt;
    }

    /** @param array<int, SubscriptionStatus>|list<SubscriptionStatus> $statuses */
    public function nextAutomaticStatus(array $statuses): ?SubscriptionStatus
    {
        $nextId = match ($this->status->id()) {
            1 => 2,
            2 => 3,
            3 => 4,
            default => null,
        };
        if ($nextId === null) {
            return null;
        }

        return self::statusById($statuses, $nextId);
    }

    /** @param array<int, SubscriptionStatus>|list<SubscriptionStatus> $statuses */
    public function nextAutomaticTransitionAt(array $statuses): ?DateTimeImmutable
    {
        $next = $this->nextAutomaticStatus($statuses);
        if ($next === null || $next->parsedDuration() === null) {
            return null;
        }

        $base = $this->status->id() === 1
            ? $this->expiresAt()
            : $this->statusChangedAt;

        return $next->parsedDuration()?->addTo($base);
    }

    public function transitionTo(SubscriptionStatus $target, DateTimeImmutable $changedAt): bool
    {
        if ($target->id() === $this->status->id()) {
            return false;
        }

        $allowed = match ($this->status->id()) {
            1 => [2],
            2 => [1, 3],
            3 => [1, 4],
            4 => [1],
            default => [],
        };
        if (!in_array($target->id(), $allowed, true)) {
            throw new LogicException(sprintf(
                'Transition from subscription status %d to %d is not allowed',
                $this->status->id(),
                $target->id(),
            ));
        }

        $this->status = $target;
        $this->statusChangedAt = $changedAt;
        $this->reminderSent = null;
        return true;
    }

    public function setStatusAdministratively(SubscriptionStatus $target, DateTimeImmutable $changedAt): bool
    {
        if ($target->id() === $this->status->id()) {
            return false;
        }
        $this->status = $target;
        $this->statusChangedAt = $changedAt;
        $this->reminderSent = null;
        return true;
    }

    public function reminderSent(): ?DateTimeImmutable
    {
        return $this->reminderSent;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return Duration::fromString($this->duration)->addTo($this->startsAt);
    }

    public function latestAutomaticSendAt(int $days): DateTimeImmutable
    {
        if ($days < 0) {
            throw new InvalidArgumentException('Latest automatic send days cannot be negative');
        }
        return Duration::fromString($days . ' DAYS')->addTo($this->expiresAt());
    }

    public function canSendAutomaticMessageAt(DateTimeImmutable $now, int $days): bool
    {
        return $now <= $this->latestAutomaticSendAt($days);
    }

    public function markReminderSent(DateTimeImmutable $sentAt): void
    {
        $this->reminderSent = $sentAt;
    }

    /** @param array<int, SubscriptionStatus>|list<SubscriptionStatus> $statuses */
    private static function statusById(array $statuses, int $id): SubscriptionStatus
    {
        foreach ($statuses as $status) {
            if ($status->id() === $id) {
                return $status;
            }
        }
        throw new LogicException('Required subscription status is not configured: ' . $id);
    }
}
