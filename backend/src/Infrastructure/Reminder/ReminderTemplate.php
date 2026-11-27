<?php

declare(strict_types=1);

namespace App\Infrastructure\Reminder;

use InvalidArgumentException;

final class ReminderTemplate
{
    /** @return array{subject:string,body:string} */
    public static function forDuration(int|string $duration, string $customerName): array
    {
        $duration = is_int($duration) ? $duration . ' MONTHS' : strtoupper($duration);
        return match ($duration) {
            '3 MONTHS', '3 MONTH' => [
                'subject' => 'Ihr Anschlussangebot: 3 Monate',
                'body' => "Hallo {$customerName},\n\nIhr 3-Monate-Testangebot ist abgelaufen. Wir laden Sie zu unserem Anschlussangebot ein.",
            ],
            '6 MONTHS', '6 MONTH' => [
                'subject' => 'Ihr Anschlussangebot: 6 Monate',
                'body' => "Hallo {$customerName},\n\nIhr Halbjahresangebot ist abgelaufen. Wir laden Sie zu unserem Anschlussangebot ein.",
            ],
            '12 MONTHS', '12 MONTH' => [
                'subject' => 'Ihr Anschlussangebot: 12 Monate',
                'body' => "Hallo {$customerName},\n\nIhr Jahresangebot ist abgelaufen. Wir laden Sie zu unserem Anschlussangebot ein.",
            ],
            default => [
                'subject' => 'Ihr Anschlussangebot',
                'body' => "Hallo {$customerName},\n\nIhr Abonnement ist abgelaufen. Wir laden Sie zu unserem Anschlussangebot ein.",
            ],
        };
    }
}
