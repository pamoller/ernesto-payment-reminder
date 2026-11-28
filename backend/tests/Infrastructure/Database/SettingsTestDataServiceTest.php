<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Database;

use App\Infrastructure\Database\SettingsTestDataService;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class SettingsTestDataServiceTest extends TestCase
{
    public function testImportsPaidInstantSubscriptionAndPreparesNextStatusStep(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE subscription_type (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            duration TEXT NOT NULL
        )');
        $pdo->exec("INSERT INTO subscription_type (id, name, duration) VALUES (9, 'Skool Instant', '0 DAYS')");
        $pdo->exec('CREATE TABLE subscription_status (id INTEGER PRIMARY KEY, duration TEXT NULL)');
        $pdo->exec("INSERT INTO subscription_status (id, duration) VALUES (1, NULL), (2, '0 DAYS'), (3, '7 DAYS'), (4, '7 DAYS')");
        $pdo->exec('CREATE TABLE subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL UNIQUE,
            subscription_type_id INTEGER NOT NULL,
            subscription_status_id INTEGER NOT NULL,
            subscription_status_changed TEXT NOT NULL,
            starts_at TEXT NOT NULL,
            reminder_sent TEXT NULL,
            reminder_claimed_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $service = new SettingsTestDataService($pdo);

        $first = $service->import('admin@example.com');
        $second = $service->import('admin@example.com');
        $row = $pdo->query(
            'SELECT c.name, c.email, s.subscription_status_id, s.starts_at
             FROM customers c JOIN subscriptions s ON s.customer_id = c.id',
        )->fetch();

        self::assertSame($first['id'], $second['id']);
        self::assertSame('Test Data', $row['name']);
        self::assertSame('admin@example.com', $row['email']);
        self::assertSame(1, (int) $row['subscription_status_id']);
        self::assertLessThan(
            (new DateTimeImmutable('now'))->format('U'),
            (new DateTimeImmutable((string) $row['starts_at']))->format('U'),
        );

        $pdo->exec('UPDATE subscriptions SET subscription_status_id = 2');
        $service->prepareNextStep();
        $nextChange = new DateTimeImmutable(
            (string) $pdo->query('SELECT subscription_status_changed FROM subscriptions')->fetchColumn(),
            new \DateTimeZone('UTC'),
        );
        self::assertLessThanOrEqual(
            (new DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-6 days 23 hours')->format('U'),
            $nextChange->format('U'),
        );
    }
}
