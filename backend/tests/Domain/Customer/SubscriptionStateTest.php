<?php

declare(strict_types=1);

namespace App\Tests\Domain\Customer;

use App\Domain\Customer\Subscription;
use App\Domain\Customer\SubscriptionStatus;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SubscriptionStateTest extends TestCase
{
    /** @var array<int, SubscriptionStatus> */
    private array $statuses;

    protected function setUp(): void
    {
        $this->statuses = [
            1 => new SubscriptionStatus(1, 'Bezahlt', null, null, null),
            2 => new SubscriptionStatus(2, 'Fällig', '0 DAYS', 'Zahlungserinnerung', 'Hallo {{customer_name}}'),
            3 => new SubscriptionStatus(3, 'Säumig', '7 DAYS', 'Mahnung', 'Mahnung für {{customer_name}}'),
            4 => new SubscriptionStatus(4, 'Beendet', '7 DAYS', 'Beendigung', 'Abo beendet'),
        ];
    }

    public function testCalculatesNextAutomaticTransitionFromDueDateForPaidStatus(): void
    {
        $subscription = $this->subscription(1, '2026-01-01');

        self::assertSame(2, $subscription->nextAutomaticStatus($this->statuses)?->id());
        self::assertSame(
            '2026-04-01T00:00:00+00:00',
            $subscription->nextAutomaticTransitionAt($this->statuses)?->format(DATE_ATOM),
        );
    }

    public function testCalculatesWaitingStatesFromLastStatusChangeAndTargetDuration(): void
    {
        $subscription = $this->subscription(2, '2026-01-01', '2026-04-01 10:00:00');

        self::assertSame(3, $subscription->nextAutomaticStatus($this->statuses)?->id());
        self::assertSame(
            '2026-04-08T10:00:00+00:00',
            $subscription->nextAutomaticTransitionAt($this->statuses)?->format(DATE_ATOM),
        );
    }

    public function testEndedStatusHasNoAutomaticTransition(): void
    {
        $subscription = $this->subscription(4, '2026-01-01', '2026-04-08 10:00:00');

        self::assertNull($subscription->nextAutomaticStatus($this->statuses));
        self::assertNull($subscription->nextAutomaticTransitionAt($this->statuses));
    }

    public function testRegularTransitionChangesStatusAndTimestampOnlyWhenDifferent(): void
    {
        $subscription = $this->subscription(1, '2026-01-01');

        self::assertTrue($subscription->transitionTo(
            $this->statuses[2],
            new DateTimeImmutable('2026-04-01T12:00:00Z'),
        ));
        self::assertSame(2, $subscription->status()->id());
        self::assertSame(
            '2026-04-01T12:00:00+00:00',
            $subscription->statusChangedAt()->format(DATE_ATOM),
        );

        self::assertFalse($subscription->transitionTo(
            $this->statuses[2],
            new DateTimeImmutable('2026-04-02T12:00:00Z'),
        ));
        self::assertSame(
            '2026-04-01T12:00:00+00:00',
            $subscription->statusChangedAt()->format(DATE_ATOM),
        );
    }

    public function testRejectsSkippingARegularState(): void
    {
        $subscription = $this->subscription(1, '2026-01-01');

        $this->expectException(LogicException::class);
        $subscription->transitionTo($this->statuses[3], new DateTimeImmutable('2026-04-01T00:00:00Z'));
    }

    private function subscription(
        int $statusId,
        string $startsAt,
        string $statusChanged = '2026-01-01 00:00:00',
    ): Subscription {
        return Subscription::reconstitute(
            7,
            1,
            3,
            new DateTimeImmutable($startsAt, new \DateTimeZone('UTC')),
            null,
            $this->statuses[$statusId],
            new DateTimeImmutable($statusChanged, new \DateTimeZone('UTC')),
        );
    }
}
