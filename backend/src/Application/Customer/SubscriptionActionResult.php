<?php

declare(strict_types=1);

namespace App\Application\Customer;

final readonly class SubscriptionActionResult
{
    public function __construct(
        public bool $changed,
        public bool $emailSent,
        public ?string $emailError = null,
    ) {
    }
}
