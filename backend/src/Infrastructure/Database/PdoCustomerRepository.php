<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\Subscription;
use DateTimeImmutable;
use PDO;

final class PdoCustomerRepository implements CustomerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Customer $customer): Customer
    {
        $now = (new DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            if ($customer->id() === null) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO customers (name, email, created_at, updated_at) VALUES (?, ?, ?, ?)',
                );
                $statement->execute([$customer->name(), $customer->email(), $now, $now]);
                $customer->setId((int) $this->pdo->lastInsertId());

                $subscription = $customer->subscription();
                $statement = $this->pdo->prepare(
                    'INSERT INTO subscriptions
                        (customer_id, duration_months, starts_at, reminder_sent, created_at, updated_at)
                     VALUES (?, ?, ?, NULL, ?, ?)',
                );
                $statement->execute([
                    $customer->id(),
                    $subscription->durationMonths(),
                    $subscription->startsAt()->format('Y-m-d H:i:s'),
                    $now,
                    $now,
                ]);
                $subscription->setId((int) $this->pdo->lastInsertId());
            } else {
                $statement = $this->pdo->prepare(
                    'UPDATE customers SET name = ?, email = ?, updated_at = ? WHERE id = ?',
                );
                $statement->execute([$customer->name(), $customer->email(), $now, $customer->id()]);

                $subscription = $customer->subscription();
                $statement = $this->pdo->prepare(
                    'UPDATE subscriptions
                     SET duration_months = ?, starts_at = ?, reminder_sent = ?, updated_at = ?
                     WHERE customer_id = ?',
                );
                $reminderSent = $subscription->reminderSent()?->format('Y-m-d H:i:s');
                $statement->execute([
                    $subscription->durationMonths(),
                    $subscription->startsAt()->format('Y-m-d H:i:s'),
                    $reminderSent,
                    $now,
                    $customer->id(),
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $customer;
    }

    public function findById(int $id): ?Customer
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.name, c.email,
                    s.id AS subscription_id, s.duration_months, s.starts_at, s.reminder_sent
             FROM customers c
             JOIN subscriptions s ON s.customer_id = c.id
             WHERE c.id = ?',
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT c.id, c.name, c.email,
                    s.id AS subscription_id, s.duration_months, s.starts_at, s.reminder_sent
             FROM customers c
             JOIN subscriptions s ON s.customer_id = c.id
             ORDER BY c.id',
        );

        $customers = [];
        while ($row = $statement->fetch()) {
            $customers[] = $this->hydrate($row);
        }
        return $customers;
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM customers WHERE id = ?');
        $statement->execute([$id]);
        return $statement->rowCount() > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Customer
    {
        $reminderSent = $row['reminder_sent'] === null
            ? null
            : new DateTimeImmutable((string) $row['reminder_sent'], new \DateTimeZone('UTC'));

        $subscription = Subscription::reconstitute(
            (int) $row['subscription_id'],
            (int) $row['duration_months'],
            new DateTimeImmutable((string) $row['starts_at'], new \DateTimeZone('UTC')),
            $reminderSent,
        );

        return Customer::reconstitute(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            $subscription,
        );
    }
}
