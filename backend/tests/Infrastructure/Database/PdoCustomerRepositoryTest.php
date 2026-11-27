<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Database;

use App\Domain\Customer\Customer;
use App\Domain\Customer\Subscription;
use App\Infrastructure\Database\PdoCustomerRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoCustomerRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoCustomerRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec(
            'CREATE TABLE customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE subscription_type (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL UNIQUE,
                duration TEXT NOT NULL UNIQUE,
                reminder TEXT NOT NULL
            )'
        );
        $this->pdo->exec(
            "INSERT INTO subscription_type (id, name, duration, reminder) VALUES
                (1, 'Skool Test', '3 MONTHS', 'Reminder Text 123'),
                (2, 'Skool Halbjahres Abo', '6 MONTHS', 'Reminder Text 123'),
                (3, 'Skool Jahres Abo', '12 MONTHS', 'Reminder Text 123')"
        );
        $this->pdo->exec(
            'CREATE TABLE subscription_status (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                duration TEXT NULL,
                subject TEXT NULL,
                message TEXT NULL
            )'
        );
        $this->pdo->exec(
            "INSERT INTO subscription_status (id, name, duration, subject, message) VALUES
                (1, 'Bezahlt', NULL, NULL, NULL),
                (2, 'Fällig', '0 DAYS', 'Zahlungserinnerung', 'Hallo'),
                (3, 'Säumig', '7 DAYS', 'Mahnung', 'Mahnung'),
                (4, 'Beendet', '7 DAYS', 'Beendigung', 'Ende')"
        );
        $this->pdo->exec(
            'CREATE TABLE subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL UNIQUE,
                subscription_type_id INTEGER NOT NULL,
                subscription_status_id INTEGER NOT NULL,
                subscription_status_changed TEXT NOT NULL,
                starts_at TEXT NOT NULL,
                reminder_sent TEXT NULL,
                reminder_claimed_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                FOREIGN KEY(subscription_type_id) REFERENCES subscription_type(id),
                FOREIGN KEY(subscription_status_id) REFERENCES subscription_status(id)
            )'
        );
        $this->repository = new PdoCustomerRepository($this->pdo);
    }

    public function testSaveFindUpdateAndDeleteCustomer(): void
    {
        $customer = Customer::create(
            'Ada Lovelace',
            'ada@example.com',
            Subscription::create(3, 12, new DateTimeImmutable('2026-01-01T00:00:00Z')),
        );

        $saved = $this->repository->save($customer);
        self::assertNotNull($saved->id());

        $found = $this->repository->findById((int) $saved->id());
        self::assertNotNull($found);
        self::assertSame('Ada Lovelace', $found->name());
        self::assertSame(3, $found->subscription()->subscriptionTypeId());
        self::assertSame(12, $found->subscription()->durationMonths());
        self::assertSame(1, $found->subscription()->status()->id());
        self::assertSame('Bezahlt', $found->subscription()->status()->name());

        $found->rename('Augusta Ada');
        $found->changeSubscription(
            Subscription::create(1, 3, new DateTimeImmutable('2026-02-01T00:00:00Z')),
        );
        $this->repository->save($found);

        $updated = $this->repository->findById((int) $saved->id());
        self::assertSame('Augusta Ada', $updated->name());
        self::assertSame(1, $updated->subscription()->subscriptionTypeId());
        self::assertSame(3, $updated->subscription()->durationMonths());

        self::assertTrue($this->repository->delete((int) $saved->id()));
        self::assertNull($this->repository->findById((int) $saved->id()));
    }

    public function testEmailClaimExpiresAndDeliveryIsStatusBound(): void
    {
        $customer = Customer::create(
            'Ada Lovelace',
            'ada@example.com',
            Subscription::create(1, 3, new DateTimeImmutable('2026-01-01T00:00:00Z')),
        );
        $saved = $this->repository->save($customer);
        $subscription = $saved->subscription();
        $subscriptionId = (int) $subscription->id();
        $claimedAt = new DateTimeImmutable('2026-04-01T12:00:00Z');
        $statusId = $subscription->status()->id();

        self::assertTrue($this->repository->claimEmailDelivery(
            $subscriptionId,
            $statusId,
            $claimedAt,
            $claimedAt->modify('-1 hour'),
        ));
        self::assertFalse($this->repository->claimEmailDelivery(
            $subscriptionId,
            $statusId,
            $claimedAt->modify('+1 minute'),
            $claimedAt->modify('-59 minutes'),
        ));

        self::assertTrue($this->repository->markEmailDelivered(
            $subscriptionId,
            $statusId,
            $claimedAt,
            $claimedAt,
        ));
        self::assertFalse($this->repository->markEmailDelivered(
            $subscriptionId,
            $statusId,
            $claimedAt,
            $claimedAt,
        ));
    }

    public function testExpiredEmailClaimCanBeReclaimed(): void
    {
        $customer = Customer::create(
            'Ada Lovelace',
            'ada@example.com',
            Subscription::create(3, 12, new DateTimeImmutable('2026-01-01T00:00:00Z')),
        );
        $saved = $this->repository->save($customer);
        $subscriptionId = (int) $saved->subscription()->id();
        $oldClaim = new DateTimeImmutable('2026-02-01T00:00:00Z');
        $claimedAt = new DateTimeImmutable('2026-02-01T02:00:00Z');
        $statement = $this->pdo->prepare('UPDATE subscriptions SET reminder_claimed_at = ? WHERE id = ?');
        $statement->execute([$oldClaim->format('Y-m-d H:i:s.u'), $subscriptionId]);

        self::assertTrue($this->repository->claimEmailDelivery(
            $subscriptionId,
            $saved->subscription()->status()->id(),
            $claimedAt,
            $claimedAt->modify('-1 hour'),
        ));
        self::assertTrue($this->repository->releaseEmailClaim(
            $subscriptionId,
            $saved->subscription()->status()->id(),
            $claimedAt,
        ));
    }

    public function testStateUpdateIsConditionalAndClearsReminderTimestamp(): void
    {
        $customer = Customer::create(
            'Ada Lovelace',
            'ada@example.com',
            Subscription::create(1, '3 MONTHS', new DateTimeImmutable('2026-01-01T00:00:00Z')),
        );
        $saved = $this->repository->save($customer);
        $subscription = $saved->subscription();
        $expectedStatus = $subscription->status()->id();
        $expectedChanged = $subscription->statusChangedAt();
        $due = new \App\Domain\Customer\SubscriptionStatus(2, 'Fällig', '0 DAYS', 'Fällig', 'Text');

        self::assertTrue($subscription->transitionTo(
            $due,
            new DateTimeImmutable('2026-04-01T00:00:00Z'),
        ));
        self::assertTrue($this->repository->trySaveSubscriptionState(
            $subscription,
            $expectedStatus,
            $expectedChanged,
        ));
        self::assertFalse($this->repository->trySaveSubscriptionState(
            $subscription,
            $expectedStatus,
            $expectedChanged,
        ));

        $reloaded = $this->repository->findById((int) $saved->id());
        self::assertSame(2, $reloaded?->subscription()->status()->id());
        self::assertNull($reloaded?->subscription()->reminderSent());
    }
}
