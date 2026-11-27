<?php

declare(strict_types=1);

namespace App\Tests\Application\Reminder;

use App\Application\Reminder\ReminderLogger;
use App\Application\Reminder\ReminderMailer;
use App\Application\Reminder\ReminderService;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\Subscription;
use App\Domain\Customer\SubscriptionStatus;
use App\Domain\Customer\SubscriptionStatusRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReminderServiceTest extends TestCase
{
    public function testTransitionsOneStateAndSendsTargetEmail(): void
    {
        $customer = $this->customer(1, '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z');
        $repo = new SchedulerRepository($customer);
        $mailer = new SchedulerMailer();
        $service = $this->service($repo, $mailer, 30);

        $result = $service->process(new DateTimeImmutable('2026-04-01T00:00:00Z'));

        self::assertSame(1, $result->transitions);
        self::assertSame(2, $customer->subscription()->status()->id());
        self::assertSame(1, $mailer->sendCount);
        self::assertSame('2026-04-01T00:00:00+00:00', $customer->subscription()->reminderSent()?->format(DATE_ATOM));
    }

    public function testRepeatedRunDoesNotDuplicateTransitionOrEmail(): void
    {
        $customer = $this->customer(2, '2026-01-01T00:00:00Z', '2026-04-01T00:00:00Z');
        $repo = new SchedulerRepository($customer);
        $mailer = new SchedulerMailer();
        $service = $this->service($repo, $mailer, 30);

        $first = $service->process(new DateTimeImmutable('2026-04-02T00:00:00Z'));
        $second = $service->process(new DateTimeImmutable('2026-04-02T01:00:00Z'));

        self::assertSame(1, $first->deliveryRetries);
        self::assertSame(0, $second->deliveryRetries);
        self::assertSame(1, $mailer->sendCount);
    }

    public function testLongOutageDoesNotSkipIntermediateState(): void
    {
        $customer = $this->customer(1, '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z');
        $service = $this->service(new SchedulerRepository($customer), new SchedulerMailer(), 365);

        $service->process(new DateTimeImmutable('2026-06-01T00:00:00Z'));
        self::assertSame(2, $customer->subscription()->status()->id());

        $service->process(new DateTimeImmutable('2026-06-08T00:00:00Z'));
        self::assertSame(3, $customer->subscription()->status()->id());
    }

    public function testSmtpFailureReleasesClaimAndRetriesOnNextRun(): void
    {
        $customer = $this->customer(2, '2026-01-01T00:00:00Z', '2026-04-01T00:00:00Z');
        $repo = new SchedulerRepository($customer);
        $mailer = new SchedulerMailer();
        $mailer->exception = new RuntimeException('SMTP unavailable');
        $service = $this->service($repo, $mailer, 30);

        $failed = $service->process(new DateTimeImmutable('2026-04-02T00:00:00Z'));
        self::assertSame(1, $failed->smtpFailures);
        self::assertTrue($repo->released);
        self::assertNull($customer->subscription()->reminderSent());

        $mailer->exception = null;
        $service->process(new DateTimeImmutable('2026-04-03T00:00:00Z'));
        self::assertSame(2, $mailer->sendCount);
        self::assertNotNull($customer->subscription()->reminderSent());
    }

    public function testExpiredClaimCanBeReclaimedAfterCrash(): void
    {
        $customer = $this->customer(2, '2026-01-01T00:00:00Z', '2026-04-01T00:00:00Z');
        $repo = new SchedulerRepository($customer);
        $repo->claimedAt = new DateTimeImmutable('2026-04-02T00:00:00Z');
        $mailer = new SchedulerMailer();

        $result = $this->service($repo, $mailer, 30)
            ->process(new DateTimeImmutable('2026-04-03T02:00:00Z'));

        self::assertSame(1, $result->sent);
        self::assertTrue($repo->claimed);
    }

    public function testActiveClaimBlocksASecondSender(): void
    {
        $customer = $this->customer(2, '2026-01-01T00:00:00Z', '2026-04-01T00:00:00Z');
        $repo = new SchedulerRepository($customer);
        $repo->claimedAt = new DateTimeImmutable('2026-04-02T00:30:00Z');
        $mailer = new SchedulerMailer();

        $result = $this->service($repo, $mailer, 30)
            ->process(new DateTimeImmutable('2026-04-02T01:00:00Z'));

        self::assertSame(0, $result->sent);
        self::assertSame(0, $mailer->sendCount);
    }

    public function testLatestSendDeadlineStopsAutomaticDeliveryButKeepsTransition(): void
    {
        $customer = $this->customer(1, '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z');
        $mailer = new SchedulerMailer();
        $result = $this->service(new SchedulerRepository($customer), $mailer, 0)
            ->process(new DateTimeImmutable('2026-05-01T00:00:00Z'));

        self::assertSame(1, $result->transitions);
        self::assertSame(1, $result->latestSendSkipped);
        self::assertSame(0, $mailer->sendCount);
        self::assertSame(2, $customer->subscription()->status()->id());
    }

    private function service(SchedulerRepository $repository, SchedulerMailer $mailer, int $latestDays): ReminderService
    {
        return new ReminderService(
            $repository,
            $mailer,
            new SchedulerLogger(),
            $latestDays,
            new SchedulerStatusRepository(),
        );
    }

    private function customer(int $statusId, string $start, string $changed): Customer
    {
        $statuses = new SchedulerStatusRepository();
        return Customer::reconstitute(
            7,
            'Ada Lovelace',
            'ada@example.com',
            Subscription::reconstitute(
                42,
                1,
                '3 MONTHS',
                new DateTimeImmutable($start),
                null,
                $statuses->findById($statusId),
                new DateTimeImmutable($changed),
            ),
        );
    }
}

final class SchedulerStatusRepository implements SubscriptionStatusRepository
{
    /** @var list<SubscriptionStatus> */
    private array $statuses;

    public function __construct()
    {
        $this->statuses = [
            new SubscriptionStatus(1, 'Bezahlt', null, null, null),
            new SubscriptionStatus(2, 'Fällig', '0 DAYS', 'Erinnerung', 'Hallo {{customer_name}}'),
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
        return $this->statuses[$id - 1] ?? null;
    }
}

final class SchedulerRepository implements CustomerRepository
{
    public ?DateTimeImmutable $claimedAt = null;
    public bool $claimed = false;
    public bool $released = false;

    public function __construct(private readonly Customer $customer)
    {
    }

    public function save(Customer $customer): Customer
    {
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
    ): bool {
        if ($this->claimedAt !== null && $this->claimedAt > $staleBefore) {
            return false;
        }
        $this->claimedAt = $claimedAt;
        $this->claimed = true;
        return true;
    }

    public function markEmailDelivered(
        int $subscriptionId,
        int $expectedStatusId,
        DateTimeImmutable $claimedAt,
        DateTimeImmutable $sentAt,
    ): bool {
        $this->claimedAt = null;
        return true;
    }

    public function releaseEmailClaim(
        int $subscriptionId,
        int $expectedStatusId,
        DateTimeImmutable $claimedAt,
    ): bool {
        $this->claimedAt = null;
        $this->released = true;
        return true;
    }
}

final class SchedulerMailer implements ReminderMailer
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

final class SchedulerLogger implements ReminderLogger
{
    public function error(string $message, ?\Throwable $exception = null): void
    {
    }
}
