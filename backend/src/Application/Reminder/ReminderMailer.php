<?php

declare(strict_types=1);

namespace App\Application\Reminder;

use App\Domain\Customer\Customer;

interface ReminderMailer
{
    public function send(Customer $customer): void;
}
