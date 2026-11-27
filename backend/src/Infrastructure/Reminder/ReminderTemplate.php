<?php

declare(strict_types=1);

namespace App\Infrastructure\Reminder;

use App\Domain\Customer\Customer;
use InvalidArgumentException;

final class ReminderTemplate
{
    /**
     * @return array{subject:string,body:string}|null
     */
    public static function forStatus(Customer $customer): ?array
    {
        $status = $customer->subscription()->status();
        $subject = $status->subject();
        $message = $status->message();
        if ($subject === null && $message === null) {
            return null;
        }
        if ($subject === null || $message === null) {
            throw new InvalidArgumentException('Subscription status subject and message must both be configured or null');
        }

        return [
            'subject' => self::render($subject, $customer),
            'body' => self::render($message, $customer),
        ];
    }

    public static function render(string $template, Customer $customer): string
    {
        $subscription = $customer->subscription();
        $values = [
            'customer_name' => $customer->name(),
            'customer_email' => $customer->email(),
            'subscription_type_id' => (string) $subscription->subscriptionTypeId(),
            'duration' => $subscription->duration(),
            'duration_months' => $subscription->durationMonths() === null
                ? ''
                : (string) $subscription->durationMonths(),
            'start_date' => $subscription->startsAt()->format('d.m.Y'),
            'expires_at' => $subscription->expiresAt()->format('d.m.Y'),
            'subscription_status' => $subscription->status()->name(),
            'subscription_status_changed' => $subscription->statusChangedAt()->format('d.m.Y H:i'),
        ];

        return (string) preg_replace_callback(
            '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
            static function (array $matches) use ($values): string {
                if (!array_key_exists($matches[1], $values)) {
                    throw new InvalidArgumentException('Unknown subscription template placeholder: ' . $matches[1]);
                }
                return $values[$matches[1]];
            },
            $template,
        );
    }

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
