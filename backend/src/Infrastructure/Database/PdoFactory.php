<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use RuntimeException;

final class PdoFactory
{
    public static function create(): PDO
    {
        self::loadEnv();
        $dsn = getenv('DB_DSN') ?: null;
        $user = getenv('DB_USER') ?: null;
        $password = getenv('DB_PASSWORD') ?: null;

        if ($dsn === null) {
            $directory = dirname(__DIR__, 3) . '/data';
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create local database directory');
            }
            $dsn = 'sqlite:' . $directory . '/ernesto.sqlite';
            $user = null;
            $password = null;
        }

        return self::connect($dsn, $user, $password);
    }

    public static function createForMigrations(): PDO
    {
        self::loadEnv();
        $dsn = getenv('DB_DSN') ?: null;
        if ($dsn === null || str_starts_with($dsn, 'sqlite:')) {
            return self::create();
        }

        $user = getenv('DB_ADMIN_USER') ?: 'ernesto_admin';
        $password = getenv('DB_ADMIN_PASSWORD') ?: '';
        if ($password === '' || $password === 'change-me') {
            throw new RuntimeException('Configure DB_ADMIN_PASSWORD to run migrations');
        }

        return self::connect($dsn, $user, $password);
    }

    private static function connect(string $dsn, ?string $user, ?string $password): PDO
    {
        $pdo = new PDO((string) $dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $pdo;
    }

    public static function loadEnv(): void
    {
        $envFile = dirname(__DIR__, 3) . '/.env';
        if (!is_file($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }
}
