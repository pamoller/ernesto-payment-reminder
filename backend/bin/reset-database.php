<?php

declare(strict_types=1);

use App\Infrastructure\Database\PdoFactory;

require __DIR__ . '/../vendor/autoload.php';

PdoFactory::loadEnv();

$dsn = getenv('DB_DSN') ?: '';
$database = getenv('DB_NAME') ?: 'ernesto_subscriptions';
$confirmation = getenv('CONFIRM_DB_RESET') ?: '';

if ($confirmation !== $database) {
    throw new RuntimeException(
        'Database reset is destructive. Set CONFIRM_DB_RESET to the exact database name: ' . $database,
    );
}

if (!str_starts_with($dsn, 'mysql:')) {
    $pdo = PdoFactory::create();
    $pdo->exec('DROP TABLE IF EXISTS subscriptions');
    $pdo->exec('DROP TABLE IF EXISTS customers');
    echo "SQLite tables reset\n";
    exit(0);
}

$bootstrapDsn = getenv('BOOTSTRAP_DSN') ?: 'mysql:host=127.0.0.1;charset=utf8mb4';
$bootstrapUser = getenv('BOOTSTRAP_USER') ?: 'root';
$bootstrapPassword = getenv('BOOTSTRAP_PASSWORD') ?: '';
$pdo = new PDO(
    $bootstrapDsn,
    $bootstrapUser,
    $bootstrapPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);

$quotedDatabase = str_replace('`', '``', $database);
$pdo->exec("DROP DATABASE IF EXISTS `{$quotedDatabase}`");
echo "Database reset: {$database}\n";
