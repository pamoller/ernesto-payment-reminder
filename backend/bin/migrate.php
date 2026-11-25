<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Database\PdoFactory;

$pdo = PdoFactory::createForMigrations();

if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS subscriptions (
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
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_subscriptions_reminder_sent
         ON subscriptions(reminder_sent)'
    );
    echo "SQLite migration completed\n";
    exit(0);
}

foreach (glob(__DIR__ . '/../migrations/*.sql') ?: [] as $migration) {
    $sql = file_get_contents($migration);
    if ($sql === false) {
        throw new RuntimeException('Migration could not be read: ' . $migration);
    }
    $pdo->exec($sql);
}

echo "MariaDB migrations completed\n";
