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
}
