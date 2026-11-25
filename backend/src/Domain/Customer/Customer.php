<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use InvalidArgumentException;

final class Customer
{
    private function __construct(
        private ?int $id,
        private string $name,
        private string $email,
        private Subscription $subscription,
    ) {
        $this->name = trim($name);
        $this->email = strtolower(trim($email));
        $this->validate();
    }

    public static function create(string $name, string $email, Subscription $subscription): self
    {
        return new self(null, $name, $email, $subscription);
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $email,
        Subscription $subscription,
    ): self {
        return new self($id, $name, $email, $subscription);
    }

    private function validate(): void
    {
        if ($this->name === '') {
            throw new InvalidArgumentException('Name is required');
        }
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email is invalid');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function subscription(): Subscription
    {
        return $this->subscription;
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Name is required');
        }
        $this->name = $name;
    }

    public function changeEmail(string $email): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email is invalid');
        }
        $this->email = $email;
    }

    public function changeSubscription(Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }
}
