<?php

declare(strict_types=1);

namespace App\Tests\Application\Customer;

use App\Application\Customer\CustomerService;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\Subscription;
use App\Domain\Customer\SubscriptionStatus;
use App\Domain\Customer\SubscriptionStatusRepository;
use App\Domain\Customer\SubscriptionType;
use App\Domain\Customer\SubscriptionTypeRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CustomerServiceTest extends TestCase
{
    public function testCreatesCustomerUsingSelectedSubscriptionType(): void
    {
        $type = new SubscriptionType(42, 'Skool Halbjahres Abo', '6 MONTHS', 'Reminder Text 123');
        $types = $this->createMock(SubscriptionTypeRepository::class);
        $types->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($type);

        $customers = $this->createMock(CustomerRepository::class);
        $customers->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Customer $customer): Customer {
                $customer->setId(7);
                $customer->subscription()->setId(12);
                return $customer;
            });

        $statuses = $this->statusRepository();
        $result = (new CustomerService($customers, $types, $statuses))->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subscription_type_id' => 42,
            'start_date' => '2026-01-01',
        ]);

        self::assertSame(42, $result['subscription_type_id']);
        self::assertSame(6, $result['duration_months']);
        self::assertSame('2026-07-01', $result['expires_at']);
        self::assertSame(1, $result['subscription_status_id']);
        self::assertSame('Bezahlt', $result['subscription_status_name']);
        self::assertSame(2, $result['next_subscription_status_id']);
        self::assertSame('Fällig', $result['next_subscription_status_name']);
        self::assertSame(
            '2026-07-01T00:00:00.000000+00:00',
            $result['next_subscription_status_at'],
        );
    }

    public function testRejectsUnknownSubscriptionType(): void
    {
        $types = $this->createMock(SubscriptionTypeRepository::class);
        $types->method('findById')->with(99)->willReturn(null);

        $customers = $this->createMock(CustomerRepository::class);
        $customers->expects(self::never())->method('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription type was not found');
        (new CustomerService(
            $customers,
            $types,
            $this->createMock(SubscriptionStatusRepository::class),
        ))->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subscription_type_id' => 99,
            'start_date' => '2026-01-01',
        ]);
    }

    public function testRenewalKeepsDurationAndClearsReminderTimestamp(): void
    {
        $subscription = Subscription::reconstitute(
            12,
            42,
            6,
            new DateTimeImmutable('2026-01-15T00:00:00Z'),
            new DateTimeImmutable('2026-03-20T09:00:00Z'),
        );
        $customer = Customer::reconstitute(7, 'Ada Lovelace', 'ada@example.com', $subscription);
        $customers = $this->createMock(CustomerRepository::class);
        $customers->expects(self::once())->method('findById')->with(7)->willReturn($customer);
        $customers->expects(self::once())->method('save')->willReturnCallback(
            static fn (Customer $saved): Customer => $saved,
        );

        $result = (new CustomerService(
            $customers,
            $this->createMock(SubscriptionTypeRepository::class),
            $this->statusRepository(),
        ))->renew(7, new DateTimeImmutable('2026-06-26T00:00:00Z'));

        self::assertNotNull($result);
        self::assertSame('2026-06-26', $result['start_date']);
        self::assertSame(6, $result['duration_months']);
        self::assertSame('2026-12-26', $result['expires_at']);
        self::assertNull($result['reminder_sent']);
        self::assertSame(1, $result['subscription_status_id']);
    }

    private function statusRepository(): SubscriptionStatusRepository
    {
        $statuses = [
            new SubscriptionStatus(1, 'Bezahlt', null, null, null),
            new SubscriptionStatus(2, 'Fällig', '0 DAYS', 'Zahlungserinnerung', 'Hallo'),
            new SubscriptionStatus(3, 'Säumig', '7 DAYS', 'Mahnung', 'Mahnung'),
            new SubscriptionStatus(4, 'Beendet', '7 DAYS', 'Beendigung', 'Ende'),
        ];
        $repository = $this->createMock(SubscriptionStatusRepository::class);
        $repository->method('findById')->willReturnCallback(
            static fn (int $id): ?SubscriptionStatus => $statuses[$id - 1] ?? null,
        );
        $repository->method('all')->willReturn($statuses);
        return $repository;
    }
}
