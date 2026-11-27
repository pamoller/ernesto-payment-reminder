<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\Subscription;
use App\Domain\Customer\SubscriptionStatus;
use App\Domain\Customer\SubscriptionType;
use DateTimeImmutable;
use PDO;
use App\Application\Common\Clock;
use App\Infrastructure\Clock\SystemClock;

final class PdoCustomerRepository implements CustomerRepository
{
    private readonly Clock $clock;

    public function __construct(private readonly PDO $pdo, ?Clock $clock = null)
    {
        $this->clock = $clock ?? new SystemClock();
    }

    public function save(Customer $customer): Customer
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

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
                        (customer_id, subscription_type_id, subscription_status_id,
                         subscription_status_changed, starts_at, reminder_sent, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NULL, ?, ?)',
                );
                $statement->execute([
                    $customer->id(),
                    $subscription->subscriptionTypeId(),
                    $subscription->status()->id(),
                    $this->formatTimestamp($subscription->statusChangedAt()),
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
                     SET subscription_type_id = ?, subscription_status_id = ?,
                         subscription_status_changed = ?, starts_at = ?, reminder_sent = ?,
                         reminder_claimed_at = NULL, updated_at = ?
                     WHERE customer_id = ?',
                );
                $reminderSent = $subscription->reminderSent() === null
                    ? null
                    : $this->formatTimestamp($subscription->reminderSent());
                $statement->execute([
                    $subscription->subscriptionTypeId(),
                    $subscription->status()->id(),
                    $this->formatTimestamp($subscription->statusChangedAt()),
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
                    s.id AS subscription_id, s.subscription_type_id, st.duration,
                    s.starts_at, s.reminder_sent, s.subscription_status_changed,
                    ss.id AS status_id, ss.name AS status_name, ss.duration AS status_duration,
                    ss.subject AS status_subject, ss.message AS status_message
             FROM customers c
             JOIN subscriptions s ON s.customer_id = c.id
             JOIN subscription_type st ON st.id = s.subscription_type_id
             JOIN subscription_status ss ON ss.id = s.subscription_status_id
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
                    s.id AS subscription_id, s.subscription_type_id, st.duration,
                    s.starts_at, s.reminder_sent, s.subscription_status_changed,
                    ss.id AS status_id, ss.name AS status_name, ss.duration AS status_duration,
                    ss.subject AS status_subject, ss.message AS status_message
             FROM customers c
             JOIN subscriptions s ON s.customer_id = c.id
             JOIN subscription_type st ON st.id = s.subscription_type_id
             JOIN subscription_status ss ON ss.id = s.subscription_status_id
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

    public function trySaveSubscriptionState(
        Subscription $subscription,
        int $expectedStatusId,
        DateTimeImmutable $expectedStatusChangedAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE subscriptions
             SET subscription_status_id = ?, subscription_status_changed = ?,
                 starts_at = ?, reminder_sent = ?, reminder_claimed_at = NULL, updated_at = ?
             WHERE id = ? AND subscription_status_id = ? AND subscription_status_changed = ?',
        );
        $reminderSent = $subscription->reminderSent() === null
            ? null
            : $this->formatTimestamp($subscription->reminderSent());
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $statement->execute([
            $subscription->status()->id(),
            $this->formatTimestamp($subscription->statusChangedAt()),
            $subscription->startsAt()->format('Y-m-d H:i:s'),
            $reminderSent,
            $now,
            $subscription->id(),
            $expectedStatusId,
            $this->formatTimestamp($expectedStatusChangedAt),
        ]);
        return $statement->rowCount() > 0;
    }

    public function claimEmailDelivery(
        int $subscriptionId,
        int $expectedStatusId,
        DateTimeImmutable $claimedAt,
        DateTimeImmutable $staleBefore,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE subscriptions
             SET reminder_claimed_at = ?, updated_at = ?
             WHERE id = ? AND subscription_status_id = ?
               AND (reminder_claimed_at IS NULL OR reminder_claimed_at < ?)',
        );
        $statement->execute([
            $this->formatTimestamp($claimedAt),
            $this->formatTimestamp($claimedAt),
            $subscriptionId,
            $expectedStatusId,
            $this->formatTimestamp($staleBefore),
        ]);
        return $statement->rowCount() > 0;
    }

    public function markEmailDelivered(
        int $subscriptionId,
        int $expectedStatusId,
        DateTimeImmutable $claimedAt,
        DateTimeImmutable $sentAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE subscriptions
             SET reminder_sent = ?, reminder_claimed_at = NULL, updated_at = ?
             WHERE id = ? AND subscription_status_id = ? AND
                   (reminder_claimed_at = ? OR reminder_claimed_at = ?)',
        );
        $statement->execute([
            $this->formatTimestamp($sentAt),
            $this->formatTimestamp($sentAt),
            $subscriptionId,
            $expectedStatusId,
            $this->formatTimestamp($claimedAt),
            $claimedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
        return $statement->rowCount() > 0;
    }

    public function releaseEmailClaim(
        int $subscriptionId,
        int $expectedStatusId,
        DateTimeImmutable $claimedAt,
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE subscriptions
             SET reminder_claimed_at = NULL, updated_at = ?
             WHERE id = ? AND subscription_status_id = ? AND
                   (reminder_claimed_at = ? OR reminder_claimed_at = ?)',
        );
        $statement->execute([
            $this->formatTimestamp($claimedAt),
            $subscriptionId,
            $expectedStatusId,
            $this->formatTimestamp($claimedAt),
            $claimedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
        return $statement->rowCount() > 0;
    }

    private function formatTimestamp(DateTimeImmutable $timestamp): string
    {
        return $timestamp->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Customer
    {
        $reminderSent = $row['reminder_sent'] === null
            ? null
            : new DateTimeImmutable((string) $row['reminder_sent'], new \DateTimeZone('UTC'));

        $subscription = Subscription::reconstitute(
            (int) $row['subscription_id'],
            (int) $row['subscription_type_id'],
            (string) $row['duration'],
            new DateTimeImmutable((string) $row['starts_at'], new \DateTimeZone('UTC')),
            $reminderSent,
            new SubscriptionStatus(
                (int) $row['status_id'],
                (string) $row['status_name'],
                $row['status_duration'] === null ? null : (string) $row['status_duration'],
                $row['status_subject'] === null ? null : (string) $row['status_subject'],
                $row['status_message'] === null ? null : (string) $row['status_message'],
            ),
            new DateTimeImmutable((string) $row['subscription_status_changed'], new \DateTimeZone('UTC')),
        );

        return Customer::reconstitute(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            $subscription,
        );
    }
}
