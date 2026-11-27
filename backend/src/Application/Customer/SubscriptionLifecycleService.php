<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Application\Reminder\ReminderClaimConflict;
use App\Application\Reminder\ReminderLogger;
use App\Application\Reminder\ReminderMailer;
use App\Domain\Customer\CustomerRepository;
use DateTimeImmutable;
use LogicException;
use Throwable;

final class SubscriptionLifecycleService
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly ReminderMailer $mailer,
        private readonly ReminderLogger $logger,
    ) {
    }

    public function resendCurrent(int $customerId, DateTimeImmutable $sentAt): ?SubscriptionActionResult
    {
        $customer = $this->customers->findById($customerId);
        if ($customer === null) {
            return null;
        }

        $status = $customer->subscription()->status();
        if ($status->subject() === null && $status->message() === null) {
            return new SubscriptionActionResult(false, false);
        }

        $subscription = $customer->subscription();
        $subscriptionId = $subscription->id();
        if ($subscriptionId === null) {
            throw new LogicException('Cannot resend email for an unsaved subscription');
        }
        if (!$this->customers->claimEmailDelivery(
            $subscriptionId,
            $status->id(),
            $sentAt,
            $sentAt->modify('-60 minutes'),
        )) {
            throw new ReminderClaimConflict('Email delivery state changed; reload and try again');
        }

        try {
            $this->mailer->send($customer);
            if (!$this->customers->markEmailDelivered(
                $subscriptionId,
                $status->id(),
                $sentAt,
                $sentAt,
            )) {
                throw new ReminderClaimConflict(
                    'Email was sent but its delivery state could not be committed',
                );
            }
            $subscription->markReminderSent($sentAt);
            return new SubscriptionActionResult(false, true);
        } catch (Throwable $exception) {
            $this->logger->error(
                'Status email resend failed for customer ' . $customerId,
                $exception,
            );
            if (!$this->customers->releaseEmailClaim(
                $subscriptionId,
                $status->id(),
                $sentAt,
            )) {
                throw new ReminderClaimConflict(
                    'Email delivery failed and previous delivery state could not be restored',
                );
            }
            return new SubscriptionActionResult(false, false, 'Status email could not be sent');
        }
    }
}
