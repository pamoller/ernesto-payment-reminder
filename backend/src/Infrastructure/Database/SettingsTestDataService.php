<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use App\Domain\Customer\Duration;

final class SettingsTestDataService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{id:int,email:string,status_id:int} */
    public function import(string $adminEmail): array
    {
        $adminEmail = trim($adminEmail);
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('ADMIN_EMAIL must be configured with a valid email address');
        }

        $type = $this->findSubscriptionTypeId('0 DAYS');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $old = $now->modify('-1 day');
        $timestamp = $old->format('Y-m-d H:i:s.u');

        $this->pdo->beginTransaction();
        try {
            $customer = $this->pdo->prepare('SELECT id FROM customers WHERE email = ?');
            $customer->execute([$adminEmail]);
            $customerId = $customer->fetchColumn();
            if ($customerId === false) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO customers (name, email, created_at, updated_at)
                     VALUES (?, ?, ?, ?)',
                );
                $statement->execute(['Test Data', $adminEmail, $timestamp, $timestamp]);
                $customerId = $this->pdo->lastInsertId();
            } else {
                $statement = $this->pdo->prepare(
                    'UPDATE customers SET name = ?, updated_at = ? WHERE id = ?',
                );
                $statement->execute(['Test Data', $timestamp, $customerId]);
            }

            $subscription = $this->pdo->prepare('SELECT id FROM subscriptions WHERE customer_id = ?');
            $subscription->execute([(int) $customerId]);
            $subscriptionId = $subscription->fetchColumn();
            if ($subscriptionId === false) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO subscriptions
                        (customer_id, subscription_type_id, subscription_status_id,
                         subscription_status_changed, starts_at, reminder_sent, created_at, updated_at)
                     VALUES (?, ?, 1, ?, ?, NULL, ?, ?)',
                );
                $statement->execute([
                    $customerId,
                    $type,
                    $timestamp,
                    $timestamp,
                    $timestamp,
                    $timestamp,
                ]);
                $subscriptionId = $this->pdo->lastInsertId();
            } else {
                $statement = $this->pdo->prepare(
                    'UPDATE subscriptions
                     SET subscription_type_id = ?, subscription_status_id = 1,
                         subscription_status_changed = ?, starts_at = ?,
                         reminder_sent = NULL, reminder_claimed_at = NULL, updated_at = ?
                     WHERE id = ?',
                );
                $statement->execute([$type, $timestamp, $timestamp, $timestamp, $subscriptionId]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'id' => (int) $subscriptionId,
            'email' => $adminEmail,
            'status_id' => 1,
        ];
    }

    public function prepareNextStep(): void
    {
        $customer = $this->pdo->prepare(
            'SELECT s.id, s.subscription_status_id
             FROM customers c
             JOIN subscriptions s ON s.customer_id = c.id
             WHERE c.name = ?',
        );
        $customer->execute(['Test Data']);
        $row = $customer->fetch();
        if ($row === false || (int) $row['subscription_status_id'] === 4) {
            return;
        }

        $statusId = (int) $row['subscription_status_id'];
        $duration = $statusId === 2
            ? $this->statusDuration(3)
            : ($statusId === 3 ? $this->statusDuration(4) : null);
        if ($duration === null) {
            return;
        }

        $changedAt = Duration::fromString($duration)->subtractFrom(
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
        $statement = $this->pdo->prepare(
            'UPDATE subscriptions
             SET subscription_status_changed = ?, reminder_sent = NULL,
                 reminder_claimed_at = NULL
             WHERE id = ?',
        );
        $statement->execute([$changedAt->format('Y-m-d H:i:s.u'), $row['id']]);
    }

    private function findSubscriptionTypeId(string $duration): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM subscription_type WHERE duration = ?');
        $statement->execute([$duration]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Subscription type with duration "' . $duration . '" is required');
        }
        return (int) $id;
    }

    private function statusDuration(int $statusId): ?string
    {
        $statement = $this->pdo->prepare('SELECT duration FROM subscription_status WHERE id = ?');
        $statement->execute([$statusId]);
        $duration = $statement->fetchColumn();
        return $duration === false ? null : (string) $duration;
    }
}
