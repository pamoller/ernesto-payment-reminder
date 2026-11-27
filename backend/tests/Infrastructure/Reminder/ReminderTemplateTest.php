<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Reminder;

use App\Infrastructure\Reminder\ReminderTemplate;
use App\Domain\Customer\Customer;
use App\Domain\Customer\Subscription;
use App\Domain\Customer\SubscriptionStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReminderTemplateTest extends TestCase
{
    public function testUsesDistinctTemplatesForAllDurations(): void
    {
        $three = ReminderTemplate::forDuration(3, 'Ada');
        $six = ReminderTemplate::forDuration(6, 'Ada');
        $twelve = ReminderTemplate::forDuration(12, 'Ada');

        self::assertStringContainsString('3 Monate', $three['subject']);
        self::assertStringContainsString('3-Monate', $three['body']);
        self::assertStringContainsString('6 Monate', $six['subject']);
        self::assertStringContainsString('Halbjahres', $six['body']);
        self::assertStringContainsString('12 Monate', $twelve['subject']);
        self::assertStringContainsString('Jahres', $twelve['body']);
        self::assertNotSame($three['subject'], $six['subject']);
        self::assertNotSame($six['subject'], $twelve['subject']);
    }

    public function testRendersStatusTemplateFromDatabaseContent(): void
    {
        $status = new SubscriptionStatus(
            2,
            'Fällig',
            '0 DAYS',
            'Erinnerung für {{customer_name}}',
            'Hallo {{customer_name}}, Ihr {{duration}} endet am {{expires_at}}.',
        );
        $customer = Customer::create(
            'Ada Lovelace',
            'ada@example.com',
            Subscription::create(1, '3 MONTHS', new \DateTimeImmutable('2026-01-01'), $status),
        );

        $template = ReminderTemplate::forStatus($customer);

        self::assertSame('Erinnerung für Ada Lovelace', $template['subject']);
        self::assertSame(
            'Hallo Ada Lovelace, Ihr 3 MONTHS endet am 01.04.2026.',
            $template['body'],
        );
    }

    public function testRejectsUnknownTemplatePlaceholder(): void
    {
        $status = new SubscriptionStatus(2, 'Fällig', '0 DAYS', 'Betreff', 'Hallo {{unknown}}');
        $customer = Customer::create(
            'Ada Lovelace',
            'ada@example.com',
            Subscription::create(1, '3 MONTHS', new \DateTimeImmutable('2026-01-01'), $status),
        );

        $this->expectException(InvalidArgumentException::class);
        ReminderTemplate::forStatus($customer);
    }
}
