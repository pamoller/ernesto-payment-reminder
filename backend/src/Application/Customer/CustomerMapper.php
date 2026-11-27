<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Domain\Customer\Customer;
use App\Domain\Customer\SubscriptionStatus;

final class CustomerMapper
{
    /**
     * @param list<SubscriptionStatus> $statuses
     * @return array<string, mixed>
     */
    public function toArray(Customer $customer, array $statuses): array
    {
        $subscription = $customer->subscription();
        $nextStatus = $subscription->nextAutomaticStatus($statuses);
        $nextTransitionAt = $subscription->nextAutomaticTransitionAt($statuses);
        return [
            'id' => $customer->id(),
            'name' => $customer->name(),
            'email' => $customer->email(),
            'subscription_type_id' => $subscription->subscriptionTypeId(),
            'duration_months' => $subscription->durationMonths(),
            'duration' => $subscription->duration(),
            'start_date' => $subscription->startsAt()->format('Y-m-d'),
            'expires_at' => $subscription->expiresAt()->format('Y-m-d'),
            'reminder_sent' => $subscription->reminderSent()?->format('Y-m-d\TH:i:s.uP'),
            'subscription_status_id' => $subscription->status()->id(),
            'subscription_status_name' => $subscription->status()->name(),
            'subscription_status_changed' => $subscription->statusChangedAt()->format('Y-m-d\TH:i:s.uP'),
            'next_subscription_status_id' => $nextStatus?->id(),
            'next_subscription_status_name' => $nextStatus?->name(),
            'next_subscription_status_at' => $nextTransitionAt?->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
