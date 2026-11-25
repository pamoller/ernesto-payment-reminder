<?php

declare(strict_types=1);

namespace App\Infrastructure\Reminder;

use InvalidArgumentException;

final class ReminderTemplate
{
    /** @return array{subject:string,body:string} */
    public static function forDuration(int $durationMonths, string $customerName): array
    {
        return match ($durationMonths) {
            3 => [
                'subject' => 'Ihr Anschlussangebot: 3 Monate',
                'body' => "Hallo {$customerName},\n\nIhr 3-Monate-Testangebot ist abgelaufen. Wir laden Sie zu unserem Anschlussangebot ein.",
            ],
            6 => [
                'subject' => 'Ihr Anschlussangebot: 6 Monate',
                'body' => "Hallo {$customerName},\n\nIhr Halbjahresangebot ist abgelaufen. Wir laden Sie zu unserem Anschlussangebot ein.",
            ],
            12 => [
                'subject' => 'Ihr Anschlussangebot: 12 Monate',
                'body' => "Hallo {$customerName},\n\nIhr Jahresangebot ist abgelaufen. Wir laden Sie zu unserem Anschlussangebot ein.",
            ],
            default => throw new InvalidArgumentException('Unknown subscription duration'),
        };
    }
}
