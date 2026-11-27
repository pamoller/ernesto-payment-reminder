<?php

declare(strict_types=1);

namespace App\Tests\Domain\Customer;

use App\Domain\Customer\Duration;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
    #[DataProvider('validDurations')]
    public function testParsesAndAppliesValidDurations(string $value, string $start, string $expected): void
    {
        $duration = Duration::fromString($value);

        self::assertSame(
            $expected,
            $duration->addTo(new DateTimeImmutable($start))->format('Y-m-d\TH:i:sP'),
        );
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function validDurations(): iterable
    {
        yield 'zero days' => ['0 DAYS', '2026-01-31T12:15:00Z', '2026-01-31T12:15:00+00:00'];
        yield 'single day' => ['1 DAY', '2026-01-31T12:15:00Z', '2026-02-01T12:15:00+00:00'];
        yield 'weeks' => ['2 WEEKS', '2026-01-01T00:00:00Z', '2026-01-15T00:00:00+00:00'];
        yield 'month clamp' => ['1 MONTH', '2026-01-31T12:15:00Z', '2026-02-28T12:15:00+00:00'];
        yield 'leap month clamp' => ['1 MONTH', '2024-01-31T12:15:00Z', '2024-02-29T12:15:00+00:00'];
        yield 'year clamp' => ['1 YEAR', '2024-02-29T08:30:00Z', '2025-02-28T08:30:00+00:00'];
    }

    #[DataProvider('invalidDurations')]
    public function testRejectsInvalidDurations(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Duration::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDurations(): iterable
    {
        yield 'negative' => ['-1 DAY'];
        yield 'fractional' => ['1.5 DAYS'];
        yield 'missing unit' => ['7'];
        yield 'unsupported unit' => ['2 HOURS'];
        yield 'unknown word' => ['soon'];
    }
}
