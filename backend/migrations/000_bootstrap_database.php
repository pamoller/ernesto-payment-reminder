<?php

declare(strict_types=1);

use App\Infrastructure\Database\PdoFactory;

PdoFactory::loadEnv();

$database = getenv('DB_NAME') ?: 'ernesto_subscriptions';
$runtimePassword = getenv('DB_PASSWORD') ?: '';
$adminPassword = getenv('DB_ADMIN_PASSWORD') ?: '';
$bootstrapDsn = getenv('BOOTSTRAP_DSN') ?: 'mysql:host=127.0.0.1;charset=utf8mb4';
$bootstrapUser = getenv('BOOTSTRAP_USER') ?: 'root';
$bootstrapPassword = getenv('BOOTSTRAP_PASSWORD') ?: '';

if ($runtimePassword === '' || $runtimePassword === 'change-me') {
    throw new RuntimeException('Set DB_PASSWORD for the ernesto runtime user');
}
if ($adminPassword === '' || $adminPassword === 'change-me') {
    throw new RuntimeException('Set DB_ADMIN_PASSWORD for the ernesto_admin user');
}

$pdo = new PDO(
    $bootstrapDsn,
    $bootstrapUser,
    $bootstrapPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);

$databaseName = str_replace('`', '``', $database);
$pdo->exec(
    "CREATE DATABASE IF NOT EXISTS `{$databaseName}` "
    . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
);

$accountHosts = array_filter(array_map(
    'trim',
    explode(',', getenv('DB_ACCOUNT_HOSTS') ?: 'localhost,127.0.0.1'),
));

foreach (array_unique($accountHosts) as $host) {
    $quotedHost = $pdo->quote($host);

    $runtimePasswordSql = $pdo->quote($runtimePassword);
    $pdo->exec(
        "CREATE USER IF NOT EXISTS 'ernesto'@{$quotedHost} IDENTIFIED BY {$runtimePasswordSql}",
    );
    $pdo->exec(
        "ALTER USER 'ernesto'@{$quotedHost} IDENTIFIED BY {$runtimePasswordSql}",
    );
    $pdo->exec(
        "GRANT SELECT, INSERT, UPDATE, DELETE "
        . "ON `{$databaseName}`.* TO 'ernesto'@{$quotedHost}",
    );

    $adminPasswordSql = $pdo->quote($adminPassword);
    $pdo->exec(
        "CREATE USER IF NOT EXISTS 'ernesto_admin'@{$quotedHost} IDENTIFIED BY {$adminPasswordSql}",
    );
    $pdo->exec(
        "ALTER USER 'ernesto_admin'@{$quotedHost} IDENTIFIED BY {$adminPasswordSql}",
    );
    $pdo->exec(
        "GRANT ALL PRIVILEGES "
        . "ON `{$databaseName}`.* TO 'ernesto_admin'@{$quotedHost}",
    );
}

$pdo->exec('FLUSH PRIVILEGES');

echo "Database and accounts created: {$database}\n";
