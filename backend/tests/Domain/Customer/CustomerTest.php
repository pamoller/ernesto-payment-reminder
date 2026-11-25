<?php

declare(strict_types=1);

namespace App\Tests\Domain\Customer;

use App\Domain\Customer\Customer;
use App\Domain\Customer\Subscription;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function testCreatesCustomerWithSubscription(): void
    {
        $subscription = Subscription::create(2, 6, new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $customer = Customer::create('Ada Lovelace', 'ADA@example.com', $subscription);

        self::assertSame('Ada Lovelace', $customer->name());
        self::assertSame('ada@example.com', $customer->email());
        self::assertSame(
            '2026-07-01T00:00:00+00:00',
            $subscription->expiresAt()->format(DATE_ATOM),
        );
    }

    public function testRejectsInvalidDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Subscription::create(1, 4, new DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Customer::create(
            'Ada Lovelace',
            'invalid',
            Subscription::create(1, 3, new DateTimeImmutable('2026-01-01T00:00:00Z')),
        );
    }

    public function testReminderIsDueOnlyAfterExpiryAndBeforeReminderWasSent(): void
    {
        $subscription = Subscription::create(1, 3, new DateTimeImmutable('2026-01-01T00:00:00Z'));

        self::assertFalse($subscription->isReminderDue(new DateTimeImmutable('2026-03-31T23:59:59Z')));
        self::assertTrue($subscription->isReminderDue(new DateTimeImmutable('2026-04-01T00:00:00Z')));

        $subscription->markReminderSent(new DateTimeImmutable('2026-04-01T00:00:00Z'));
        self::assertFalse($subscription->isReminderDue(new DateTimeImmutable('2026-04-02T00:00:00Z')));
    }
}
