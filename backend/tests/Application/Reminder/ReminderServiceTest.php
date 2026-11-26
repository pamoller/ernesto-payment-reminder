<?php

declare(strict_types=1);

namespace App\Tests\Application\Reminder;

use App\Application\Reminder\ReminderService;
use App\Application\Reminder\ReminderMailer;
use App\Application\Reminder\ReminderLogger;
use App\Application\Reminder\ReminderClaimConflict;
use App\Application\Reminder\ReminderDeliveryFailed;
use App\Application\Reminder\ReminderAuthorizationRequired;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\Subscription;
use DateTimeImmutable;
use RuntimeException;
use PHPUnit\Framework\TestCase;

final class ReminderServiceTest extends TestCase
{
    public function testDoesNotSendBeforeExpiry(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(3, '2026-03-01T00:00:00Z', null),
        ]);
        $mailer = new FakeReminderMailer();

        $result = (new ReminderService($repo, $mailer, new FakeReminderLogger()))
            ->process(new DateTimeImmutable('2026-03-31T00:00:00Z'));

        self::assertSame(0, $result->sent);
        self::assertSame(0, $mailer->sendCount);
    }

    public function testSendsOnceWhenDue(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(3, '2026-01-01T00:00:00Z', null),
        ]);
        $mailer = new FakeReminderMailer();

        $result = (new ReminderService($repo, $mailer, new FakeReminderLogger()))
            ->process(new DateTimeImmutable('2026-04-01T00:00:00Z'));

        self::assertSame(1, $result->sent);
        self::assertSame(1, $mailer->sendCount);
    }

    public function testDoesNotSendWhenAlreadyMarked(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(
                3,
                '2026-01-01T00:00:00Z',
                new DateTimeImmutable('2026-04-01T00:00:00Z'),
            ),
        ]);
        $mailer = new FakeReminderMailer();

        $result = (new ReminderService($repo, $mailer, new FakeReminderLogger()))
            ->process(new DateTimeImmutable('2026-04-02T00:00:00Z'));

        self::assertSame(0, $result->sent);
        self::assertSame(0, $mailer->sendCount);
    }

    public function testFailedInitialClaimDoesNotSend(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(3, '2026-01-01T00:00:00Z', null),
        ]);
        $repo->claimResult = false;
        $mailer = new FakeReminderMailer();

        $result = (new ReminderService($repo, $mailer, new FakeReminderLogger()))
            ->process(new DateTimeImmutable('2026-04-01T00:00:00Z'));

        self::assertSame(0, $result->sent);
        self::assertSame(0, $mailer->sendCount);
    }

    public function testSmtpExceptionClearsClaim(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(3, '2026-01-01T00:00:00Z', null),
        ]);
        $mailer = new FakeReminderMailer();
        $mailer->exception = new RuntimeException('SMTP unavailable');

        $result = (new ReminderService($repo, $mailer, new FakeReminderLogger()))
            ->process(new DateTimeImmutable('2026-04-01T00:00:00Z'));

        self::assertSame(0, $result->sent);
        self::assertSame(1, $repo->clearCount);
        self::assertSame(1, $result->smtpFailures);
    }

    public function testSmtpExceptionAndFailedResetIsReported(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(3, '2026-01-01T00:00:00Z', null),
        ]);
        $repo->clearResult = false;
        $mailer = new FakeReminderMailer();
        $mailer->exception = new RuntimeException('SMTP unavailable');
        $logger = new FakeReminderLogger();

        $result = (new ReminderService($repo, $mailer, $logger))
            ->process(new DateTimeImmutable('2026-04-01T00:00:00Z'));

        self::assertSame(1, $result->resetFailures);
        self::assertGreaterThan(0, $logger->count);
    }

    public function testManualReminderSendsBeforeExpiryWhenNoPreviousReminderExists(): void
    {
        $customer = $this->customer(12, '2026-03-01T00:00:00Z', null);
        $repo = new FakeReminderRepository([$customer]);
        $mailer = new FakeReminderMailer();
        $sentAt = new DateTimeImmutable('2026-03-20T12:00:00Z');

        $result = (new ReminderService($repo, $mailer, new FakeReminderLogger()))
            ->sendManually(7, $sentAt);

        self::assertNotNull($result);
        self::assertSame($sentAt->format(DATE_ATOM), $result->format(DATE_ATOM));
        self::assertSame(1, $mailer->sendCount);
        self::assertNull($repo->expectedPreviousSentAt);
        self::assertSame($sentAt->format(DATE_ATOM), $repo->claimedAt?->format(DATE_ATOM));
    }

    public function testAuthorisedRepeatedReminderOnlyResetsReminderSent(): void
    {
        $previousSent = new DateTimeImmutable('2026-03-15T00:00:00Z');
        $customer = $this->customer(12, '2026-03-01T00:00:00Z', $previousSent);
        $repo = new FakeReminderRepository([$customer]);
        $mailer = new FakeReminderMailer();

        $result = (new ReminderService($repo, $mailer, new FakeReminderLogger()))
            ->sendManually(7, new DateTimeImmutable('2026-03-20T12:00:00Z'), true);

        self::assertNull($result);
        self::assertSame(1, $repo->clearCount);
        self::assertNull($customer->subscription()->reminderSent());
        self::assertSame(0, $mailer->sendCount);
    }

    public function testRepeatedReminderResetRequiresAuthorisation(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(12, '2026-03-01T00:00:00Z', new DateTimeImmutable('2026-03-15T00:00:00Z')),
        ]);
        $mailer = new FakeReminderMailer();

        $this->expectException(ReminderAuthorizationRequired::class);
        try {
            (new ReminderService($repo, $mailer, new FakeReminderLogger()))
                ->sendManually(7, new DateTimeImmutable('2026-03-20T12:00:00Z'));
        } finally {
            self::assertSame(0, $mailer->sendCount);
            self::assertSame(0, $repo->clearCount);
        }
    }

    public function testManualReminderDoesNotSendIfAtomicUpdateLosesRace(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(3, '2026-01-01T00:00:00Z', null),
        ]);
        $repo->manualClaimResult = false;
        $mailer = new FakeReminderMailer();

        $this->expectException(ReminderClaimConflict::class);
        try {
            (new ReminderService($repo, $mailer, new FakeReminderLogger()))
                ->sendManually(7, new DateTimeImmutable('2026-02-01T00:00:00Z'));
        } finally {
            self::assertSame(0, $mailer->sendCount);
        }
    }

    public function testManualSmtpFailureRestoresNullReminderDate(): void
    {
        $repo = new FakeReminderRepository([
            $this->customer(12, '2026-03-01T00:00:00Z', null),
        ]);
        $mailer = new FakeReminderMailer();
        $mailer->exception = new RuntimeException('SMTP unavailable');

        $this->expectException(ReminderDeliveryFailed::class);
        try {
            (new ReminderService($repo, $mailer, new FakeReminderLogger()))
                ->sendManually(7, new DateTimeImmutable('2026-03-20T12:00:00Z'));
        } finally {
            self::assertNull($repo->restoredSentAt);
        }
    }

    /** @return Customer */
    private function customer(
        int $duration,
        string $start,
        ?DateTimeImmutable $sentAt,
    ): Customer {
        $subscription = Subscription::reconstitute(
            42,
            match ($duration) {
                3 => 1,
                6 => 2,
                12 => 3,
                default => throw new \InvalidArgumentException('Unsupported duration'),
            },
            $duration,
            new DateTimeImmutable($start),
            $sentAt,
        );
        return Customer::reconstitute(
            7,
            'Ada Lovelace',
            'ada@example.com',
            $subscription,
        );
    }
}

final class FakeReminderRepository implements CustomerRepository
{
    public bool $claimResult = true;
    public bool $clearResult = true;
    public bool $manualClaimResult = true;
    public int $clearCount = 0;
    public ?DateTimeImmutable $expectedPreviousSentAt = null;
    public ?DateTimeImmutable $claimedAt = null;
    public ?DateTimeImmutable $restoredSentAt = null;

    /** @param list<Customer> $customers */
    public function __construct(private array $customers)
    {
    }

    public function save(Customer $customer): Customer
    {
        return $customer;
    }

    public function findById(int $id): ?Customer
    {
        foreach ($this->customers as $customer) {
            if ($customer->id() === $id) {
                return $customer;
            }
        }
        return null;
    }

    public function all(): array
    {
        return $this->customers;
    }

    public function delete(int $id): bool
    {
        return false;
    }

    public function tryClaimReminder(int $subscriptionId, DateTimeImmutable $sentAt): bool
    {
        return $this->claimResult;
    }

    public function clearReminderSent(int $subscriptionId, DateTimeImmutable $sentAt): bool
    {
        $this->clearCount++;
        return $this->clearResult;
    }

    public function trySetReminderSent(
        int $subscriptionId,
        ?DateTimeImmutable $expectedSentAt,
        DateTimeImmutable $sentAt,
    ): bool {
        $this->expectedPreviousSentAt = $expectedSentAt;
        $this->claimedAt = $sentAt;
        return $this->manualClaimResult;
    }

    public function restoreReminderSent(
        int $subscriptionId,
        DateTimeImmutable $claimedAt,
        ?DateTimeImmutable $previousSentAt,
    ): bool {
        $this->restoredSentAt = $previousSentAt;
        return $this->clearResult;
    }
}

final class FakeReminderMailer implements ReminderMailer
{
    public int $sendCount = 0;
    public ?\Throwable $exception = null;

    public function send(Customer $customer): void
    {
        $this->sendCount++;
        if ($this->exception !== null) {
            throw $this->exception;
        }
    }
}

final class FakeReminderLogger implements ReminderLogger
{
    public int $count = 0;

    public function error(string $message, ?\Throwable $exception = null): void
    {
        $this->count++;
    }
}
