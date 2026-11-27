<?php

declare(strict_types=1);

namespace App\Tests\Application\Customer;

use App\Application\Customer\SubscriptionLifecycleService;
use App\Application\Reminder\ReminderLogger;
use App\Application\Reminder\ReminderMailer;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\Subscription;
use App\Domain\Customer\SubscriptionStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class SubscriptionLifecycleServiceTest extends TestCase
{
    public function testResendDoesNotChangeSubscriptionStateOrDates(): void
    {
        $customer = $this->customer();
        $repository = new LifecycleRepository($customer);
        $mailer = new LifecycleMailer();
        $statusChanged = $customer->subscription()->statusChangedAt();
        $startsAt = $customer->subscription()->startsAt();

        $result = $this->service($repository, $mailer)
            ->resendCurrent(7, new DateTimeImmutable('2026-06-20T10:30:00Z'));

        self::assertTrue($result?->emailSent);
        self::assertSame($startsAt, $customer->subscription()->startsAt());
        self::assertSame($statusChanged, $customer->subscription()->statusChangedAt());
        self::assertSame(2, $customer->subscription()->status()->id());
    }

    public function testResendRestoresPreviousDeliveryTimestampOnSmtpFailure(): void
    {
        $previous = new DateTimeImmutable('2026-06-02T08:00:00Z');
        $customer = $this->customer($previous);
        $repository = new LifecycleRepository($customer);
        $mailer = new LifecycleMailer();
        $mailer->exception = new RuntimeException('SMTP unavailable');

        $result = $this->service($repository, $mailer)
            ->resendCurrent(7, new DateTimeImmutable('2026-06-20T10:30:00Z'));

        self::assertFalse($result?->emailSent);
        self::assertNotNull($result->emailError);
        self::assertSame($previous, $customer->subscription()->reminderSent());
    }

    private function service(LifecycleRepository $repository, LifecycleMailer $mailer): SubscriptionLifecycleService
    {
        return new SubscriptionLifecycleService($repository, $mailer, new LifecycleLogger());
    }

    private function customer(?DateTimeImmutable $reminderSent = null): Customer
    {
        return Customer::reconstitute(
            7,
            'Ada Lovelace',
            'ada@example.com',
            Subscription::reconstitute(
                42,
                1,
                '3 MONTHS',
                new DateTimeImmutable('2026-01-01T00:00:00Z'),
                $reminderSent,
                new SubscriptionStatus(2, 'Fällig', '0 DAYS', 'Erinnerung', 'Hallo {{customer_name}}'),
                new DateTimeImmutable('2026-04-01T00:00:00Z'),
            ),
        );
    }
}

final class LifecycleRepository implements CustomerRepository
{
    public function __construct(public Customer $customer)
    {
    }

    public function save(Customer $customer): Customer
    {
        $this->customer = $customer;
        return $customer;
    }

    public function findById(int $id): ?Customer
    {
        return $id === $this->customer->id() ? $this->customer : null;
    }

    public function all(): array
    {
        return [$this->customer];
    }

    public function delete(int $id): bool
    {
        return false;
    }

    public function trySaveSubscriptionState(
        Subscription $subscription,
        int $expectedStatusId,
        DateTimeImmutable $expectedStatusChangedAt,
    ): bool {
        return true;
    }

    public function claimEmailDelivery(
        int $subscriptionId,
        int $expectedStatusId,
        DateTimeImmutable $claimedAt,
        DateTimeImmutable $staleBefore,
    ): bool
    {
        return true;
    }

    public function markEmailDelivered(
        int $subscriptionId,
        int $expectedStatusId,
        DateTimeImmutable $claimedAt,
        DateTimeImmutable $sentAt,
    ): bool
    {
        return true;
    }

    public function releaseEmailClaim(
        int $subscriptionId,
        int $expectedStatusId,
        DateTimeImmutable $claimedAt,
    ): bool {
        return true;
    }
}

final class LifecycleMailer implements ReminderMailer
{
    public ?Throwable $exception = null;

    public function send(Customer $customer): void
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }
    }
}

final class LifecycleLogger implements ReminderLogger
{
    public function error(string $message, ?Throwable $exception = null): void
    {
    }
}
