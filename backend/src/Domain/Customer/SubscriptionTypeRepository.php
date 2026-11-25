<?php

declare(strict_types=1);

namespace App\Domain\Customer;

interface SubscriptionTypeRepository
{
    /** @return list<SubscriptionType> */
    public function all(): array;

    public function findById(int $id): ?SubscriptionType;
}
