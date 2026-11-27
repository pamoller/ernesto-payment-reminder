<?php

declare(strict_types=1);

namespace App\Domain\Customer;

interface CustomerRepository
{
    public function save(Customer $customer): Customer;

    public function findById(int $id): ?Customer;

    /** @return list<Customer> */
    public function all(): array;

    public function delete(int $id): bool;

    public function trySaveSubscriptionState(
        Subscription $subscription,
        int $expectedStatusId,
        \DateTimeImmutable $expectedStatusChangedAt,
    ): bool;

    public function tryClaimReminder(int $subscriptionId, \DateTimeImmutable $sentAt): bool;

    public function clearReminderSent(int $subscriptionId, \DateTimeImmutable $sentAt): bool;

    public function trySetReminderSent(
        int $subscriptionId,
        ?\DateTimeImmutable $expectedSentAt,
        \DateTimeImmutable $sentAt,
        ?int $expectedStatusId = null,
    ): bool;

    public function restoreReminderSent(
        int $subscriptionId,
        \DateTimeImmutable $claimedAt,
        ?\DateTimeImmutable $previousSentAt,
        ?int $expectedStatusId = null,
    ): bool;
}
