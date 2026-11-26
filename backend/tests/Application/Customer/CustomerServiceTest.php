<?php

declare(strict_types=1);

namespace App\Tests\Application\Customer;

use App\Application\Customer\CustomerService;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\SubscriptionType;
use App\Domain\Customer\SubscriptionTypeRepository;
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

        $result = (new CustomerService($customers, $types))->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subscription_type_id' => 42,
            'start_date' => '2026-01-01',
        ]);

        self::assertSame(42, $result['subscription_type_id']);
        self::assertSame(6, $result['duration_months']);
        self::assertSame('2026-07-01', $result['expires_at']);
    }

    public function testRejectsUnknownSubscriptionType(): void
    {
        $types = $this->createMock(SubscriptionTypeRepository::class);
        $types->method('findById')->with(99)->willReturn(null);

        $customers = $this->createMock(CustomerRepository::class);
        $customers->expects(self::never())->method('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription type was not found');
        (new CustomerService($customers, $types))->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subscription_type_id' => 99,
            'start_date' => '2026-01-01',
        ]);
    }
}
