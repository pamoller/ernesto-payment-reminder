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
            'CREATE TABLE subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL UNIQUE,
                duration_months INTEGER NOT NULL,
                starts_at TEXT NOT NULL,
                reminder_sent TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
            )'
        );
        $this->repository = new PdoCustomerRepository($this->pdo);
    }

    public function testSaveFindUpdateAndDeleteCustomer(): void
    {
        $customer = Customer::create(
            'Ada Lovelace',
            'ada@example.com',
            Subscription::create(12, new DateTimeImmutable('2026-01-01T00:00:00Z')),
        );

        $saved = $this->repository->save($customer);
        self::assertNotNull($saved->id());

        $found = $this->repository->findById((int) $saved->id());
        self::assertNotNull($found);
        self::assertSame('Ada Lovelace', $found->name());
        self::assertSame(12, $found->subscription()->durationMonths());

        $found->rename('Augusta Ada');
        $found->changeSubscription(
            Subscription::create(3, new DateTimeImmutable('2026-02-01T00:00:00Z')),
        );
        $this->repository->save($found);

        $updated = $this->repository->findById((int) $saved->id());
        self::assertSame('Augusta Ada', $updated->name());
        self::assertSame(3, $updated->subscription()->durationMonths());

        self::assertTrue($this->repository->delete((int) $saved->id()));
        self::assertNull($this->repository->findById((int) $saved->id()));
    }

    public function testReminderClaimIsAtomicAndResetIsConditional(): void
    {
        $customer = Customer::create(
            'Ada Lovelace',
            'ada@example.com',
            Subscription::create(3, new DateTimeImmutable('2026-01-01T00:00:00Z')),
        );
        $saved = $this->repository->save($customer);
        $subscription = $saved->subscription();
        $subscriptionId = (int) $subscription->id();
        $claimedAt = new DateTimeImmutable('2026-04-01T12:00:00Z');

        self::assertTrue($this->repository->tryClaimReminder($subscriptionId, $claimedAt));
        self::assertFalse($this->repository->tryClaimReminder($subscriptionId, $claimedAt));

        self::assertTrue($this->repository->clearReminderSent(
            $subscriptionId,
            $claimedAt,
        ));
        self::assertFalse($this->repository->clearReminderSent(
            $subscriptionId,
            $claimedAt,
        ));
    }
}
