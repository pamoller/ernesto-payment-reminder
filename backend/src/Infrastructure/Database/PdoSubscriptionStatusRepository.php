<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Customer\SubscriptionStatus;
use App\Domain\Customer\SubscriptionStatusRepository;
use PDO;

final class PdoSubscriptionStatusRepository implements SubscriptionStatusRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, duration, subject, message
             FROM subscription_status
             ORDER BY id',
        );

        $statuses = [];
        while ($row = $statement->fetch()) {
            $statuses[] = $this->hydrate($row);
        }
        return $statuses;
    }

    public function findById(int $id): ?SubscriptionStatus
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, duration, subject, message
             FROM subscription_status
             WHERE id = ?',
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): SubscriptionStatus
    {
        return new SubscriptionStatus(
            (int) $row['id'],
            (string) $row['name'],
            $row['duration'] === null ? null : (string) $row['duration'],
            $row['subject'] === null ? null : (string) $row['subject'],
            $row['message'] === null ? null : (string) $row['message'],
        );
    }
}
