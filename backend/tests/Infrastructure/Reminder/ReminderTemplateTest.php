<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Reminder;

use App\Infrastructure\Reminder\ReminderTemplate;
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
}
