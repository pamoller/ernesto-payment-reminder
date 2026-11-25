<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Customer\SubscriptionType;
use App\Domain\Customer\SubscriptionTypeRepository;
use PDO;

final class PdoSubscriptionTypeRepository implements SubscriptionTypeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, duration, reminder
             FROM subscription_type
             ORDER BY id',
        );

        $types = [];
        while ($row = $statement->fetch()) {
            $types[] = $this->hydrate($row);
        }
        return $types;
    }

    public function findById(int $id): ?SubscriptionType
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, duration, reminder
             FROM subscription_type
             WHERE id = ?',
        );
        $statement->execute([$id]);
        $row = $statement->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): SubscriptionType
    {
        return new SubscriptionType(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['duration'],
            (string) $row['reminder'],
        );
    }
}
