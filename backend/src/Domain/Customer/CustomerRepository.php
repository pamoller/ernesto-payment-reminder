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

    public function claimEmailDelivery(
        int $subscriptionId,
        int $expectedStatusId,
        \DateTimeImmutable $claimedAt,
        \DateTimeImmutable $staleBefore,
    ): bool;

    public function markEmailDelivered(
        int $subscriptionId,
        int $expectedStatusId,
        \DateTimeImmutable $claimedAt,
        \DateTimeImmutable $sentAt,
    ): bool;

    public function releaseEmailClaim(
        int $subscriptionId,
        int $expectedStatusId,
        \DateTimeImmutable $claimedAt,
    ): bool;
}
