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
        'CREATE TABLE IF NOT EXISTS subscription_type (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            duration TEXT NOT NULL,
            reminder TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT OR IGNORE INTO subscription_type (name, duration, reminder) VALUES
            ('Skool Test', '3 MONTHS', 'Reminder Text 123'),
            ('Skool Halbjahres Abo', '6 MONTHS', 'Reminder Text 123'),
            ('Skool Jahres Abo', '12 MONTHS', 'Reminder Text 123')"
    );
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS uk_subscription_type_duration
         ON subscription_type(duration)'
    );

    $subscriptionColumns = $pdo->query('PRAGMA table_info(subscriptions)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('duration_months', $subscriptionColumns, true)) {
        $pdo->exec('DELETE FROM customers');
        $pdo->exec('DROP TABLE subscriptions');
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL UNIQUE,
            subscription_type_id INTEGER NOT NULL,
            starts_at TEXT NOT NULL,
            reminder_sent TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY(subscription_type_id) REFERENCES subscription_type(id)
        )'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_subscriptions_reminder_sent
         ON subscriptions(reminder_sent)'
    );
    echo "SQLite migration completed\n";
    exit(0);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) NOT NULL,
        applied_at DATETIME NOT NULL,
        PRIMARY KEY (version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$migrationStatus = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
$recordMigration = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
foreach (glob(__DIR__ . '/../migrations/*.sql') ?: [] as $migration) {
    $version = basename($migration);
    $migrationStatus->execute([$version]);
    if ($migrationStatus->fetchColumn() !== false) {
        continue;
    }

    $sql = file_get_contents($migration);
    if ($sql === false) {
        throw new RuntimeException('Migration could not be read: ' . $migration);
    }
    $pdo->exec($sql);
    $recordMigration->execute([$version, gmdate('Y-m-d H:i:s')]);
}

echo "MariaDB migrations completed\n";
