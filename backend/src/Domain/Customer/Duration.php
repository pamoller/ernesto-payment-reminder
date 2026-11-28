<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Duration
{
    private function __construct(
        private int $amount,
        private string $unit,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^([0-9]+)\s+(DAY|DAYS|WEEK|WEEKS|MONTH|MONTHS|YEAR|YEARS)$/i', trim($value), $matches) !== 1) {
            throw new InvalidArgumentException('Duration must use DAY, WEEK, MONTH or YEAR with a non-negative integer');
        }

        $unit = match (strtoupper($matches[2])) {
            'DAY', 'DAYS' => 'DAY',
            'WEEK', 'WEEKS' => 'WEEK',
            'MONTH', 'MONTHS' => 'MONTH',
            'YEAR', 'YEARS' => 'YEAR',
        };

        return new self((int) $matches[1], $unit);
    }

    public function addTo(DateTimeImmutable $date): DateTimeImmutable
    {
        if ($this->amount === 0) {
            return $date;
        }

        return match ($this->unit) {
            'DAY' => $date->modify('+' . $this->amount . ' days'),
            'WEEK' => $date->modify('+' . ($this->amount * 7) . ' days'),
            'MONTH' => $this->addCalendarMonths($date, $this->amount),
            'YEAR' => $this->addCalendarMonths($date, $this->amount * 12),
        };
    }

    public function subtractFrom(DateTimeImmutable $date): DateTimeImmutable
    {
        if ($this->amount === 0) {
            return $date;
        }

        return match ($this->unit) {
            'DAY' => $date->modify('-' . $this->amount . ' days'),
            'WEEK' => $date->modify('-' . ($this->amount * 7) . ' days'),
            'MONTH' => $this->addCalendarMonths($date, -$this->amount),
            'YEAR' => $this->addCalendarMonths($date, -$this->amount * 12),
        };
    }

    public function __toString(): string
    {
        return $this->amount . ' ' . $this->unit . ($this->amount === 1 ? '' : 'S');
    }

    private function addCalendarMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $absoluteMonth = ($year * 12 + $month - 1) + $months;
        $targetYear = intdiv($absoluteMonth, 12);
        $targetMonth = $absoluteMonth % 12 + 1;
        $lastDay = (int) $date->setDate($targetYear, $targetMonth, 1)->format('t');

        return $date->setDate($targetYear, $targetMonth, min($day, $lastDay));
    }
}
