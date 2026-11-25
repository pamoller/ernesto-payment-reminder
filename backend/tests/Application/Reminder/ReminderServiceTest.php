<?php

declare(strict_types=1);

namespace App\Tests\Application\Reminder;

use App\Application\Reminder\ReminderService;
use App\Application\Reminder\ReminderMailer;
use App\Application\Reminder\ReminderLogger;
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

    /** @return Customer */
    private function customer(
        int $duration,
        string $start,
        ?DateTimeImmutable $sentAt,
    ): Customer {
        $subscription = Subscription::reconstitute(
            42,
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
    public int $clearCount = 0;

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
