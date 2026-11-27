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
use App\Domain\Customer\SubscriptionStatusRepository;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class SubscriptionLifecycleServiceTest extends TestCase
{
    public function testPaymentTransitionsToPaidRenewsAndSendsStatusEmail(): void
    {
        $repository = new LifecycleRepository($this->customer(2));
        $mailer = new LifecycleMailer();
        $service = $this->service($repository, $mailer);

        $result = $service->registerPayment(7, new DateTimeImmutable('2026-06-20T10:30:00Z'));

        self::assertNotNull($result);
        self::assertTrue($result->changed);
        self::assertTrue($result->emailSent);
        self::assertSame(1, $repository->customer->subscription()->status()->id());
        self::assertSame('2026-06-20', $repository->customer->subscription()->startsAt()->format('Y-m-d'));
        self::assertSame('2026-06-20 10:30:00', $repository->customer->subscription()->statusChangedAt()->format('Y-m-d H:i:s'));
        self::assertSame(1, $mailer->sendCount);
        self::assertNotNull($repository->customer->subscription()->reminderSent());
    }

    public function testEmailFailureDoesNotRollbackPersistedState(): void
    {
        $repository = new LifecycleRepository($this->customer(3));
        $mailer = new LifecycleMailer();
        $mailer->exception = new RuntimeException('SMTP unavailable');
        $result = $this->service($repository, $mailer)
            ->registerPayment(7, new DateTimeImmutable('2026-06-20T10:30:00Z'));

        self::assertTrue($result?->changed);
        self::assertFalse($result->emailSent);
        self::assertNotNull($result->emailError);
        self::assertSame(1, $repository->customer->subscription()->status()->id());
        self::assertNull($repository->customer->subscription()->reminderSent());
        self::assertTrue($repository->stateSaved);
    }

    public function testPaymentRejectsAnAlreadyPaidSubscription(): void
    {
        $service = $this->service(new LifecycleRepository($this->customer(1)), new LifecycleMailer());

        $this->expectException(LogicException::class);
        $service->registerPayment(7, new DateTimeImmutable('2026-06-20T10:30:00Z'));
    }

    public function testResendDoesNotChangeSubscriptionStateOrDates(): void
    {
        $customer = $this->customer(2);
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
        self::assertFalse($repository->stateSaved);
    }

    private function service(LifecycleRepository $repository, LifecycleMailer $mailer): SubscriptionLifecycleService
    {
        return new SubscriptionLifecycleService($repository, new LifecycleStatuses(), $mailer, new LifecycleLogger());
    }

    private function customer(int $statusId): Customer
    {
        $statuses = new LifecycleStatuses();
        return Customer::reconstitute(
            7,
            'Ada Lovelace',
            'ada@example.com',
            Subscription::reconstitute(
                42,
                1,
                '3 MONTHS',
                new DateTimeImmutable('2026-01-01T00:00:00Z'),
                null,
                $statuses->findById($statusId),
                new DateTimeImmutable('2026-06-01T00:00:00Z'),
            ),
        );
    }
}

final class LifecycleStatuses implements SubscriptionStatusRepository
{
    /** @var list<SubscriptionStatus> */
    private array $statuses;

    public function __construct()
    {
        $this->statuses = [
            new SubscriptionStatus(1, 'Bezahlt', null, 'Zahlungsbestätigung', 'Danke {{customer_name}}'),
            new SubscriptionStatus(2, 'Fällig', '0 DAYS', 'Zahlungserinnerung', 'Hallo {{customer_name}}'),
            new SubscriptionStatus(3, 'Säumig', '7 DAYS', 'Mahnung', 'Mahnung {{customer_name}}'),
            new SubscriptionStatus(4, 'Beendet', '7 DAYS', 'Beendigung', 'Ende {{customer_name}}'),
        ];
    }

    public function all(): array
    {
        return $this->statuses;
    }

    public function findById(int $id): ?SubscriptionStatus
    {
        foreach ($this->statuses as $status) {
            if ($status->id() === $id) {
                return $status;
            }
        }
        return null;
    }
}

final class LifecycleRepository implements CustomerRepository
{
    public bool $stateSaved = false;

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
        $this->stateSaved = true;
        return true;
    }

    public function tryClaimReminder(int $subscriptionId, DateTimeImmutable $sentAt): bool
    {
        return true;
    }

    public function clearReminderSent(int $subscriptionId, DateTimeImmutable $sentAt): bool
    {
        return true;
    }

    public function trySetReminderSent(
        int $subscriptionId,
        ?DateTimeImmutable $expectedSentAt,
        DateTimeImmutable $sentAt,
        ?int $expectedStatusId = null,
    ): bool {
        return true;
    }

    public function restoreReminderSent(
        int $subscriptionId,
        DateTimeImmutable $claimedAt,
        ?DateTimeImmutable $previousSentAt,
        ?int $expectedStatusId = null,
    ): bool {
        return true;
    }
}

final class LifecycleMailer implements ReminderMailer
{
    public int $sendCount = 0;
    public ?Throwable $exception = null;

    public function send(Customer $customer): void
    {
        $this->sendCount++;
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
