<?php

declare(strict_types=1);

namespace App\Domain\Customer;

interface SubscriptionStatusRepository
{
    /** @return list<SubscriptionStatus> */
    public function all(): array;

    public function findById(int $id): ?SubscriptionStatus;
}
