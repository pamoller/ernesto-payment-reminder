<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Application\Reminder\ReminderClaimConflict;
use App\Application\Reminder\ReminderLogger;
use App\Application\Reminder\ReminderMailer;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\SubscriptionStatus;
use App\Domain\Customer\SubscriptionStatusRepository;
use DateTimeImmutable;
use LogicException;
use OutOfBoundsException;
use Throwable;

final class SubscriptionLifecycleService
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly SubscriptionStatusRepository $statuses,
        private readonly ReminderMailer $mailer,
        private readonly ReminderLogger $logger,
    ) {
    }

    public function registerPayment(int $customerId, DateTimeImmutable $paidAt): ?SubscriptionActionResult
    {
        return $this->change(
            $customerId,
            static fn (Customer $customer, SubscriptionStatus $paid) =>
                $customer->subscription()->registerPayment($paid, $paidAt),
            1,
            true,
        );
    }

    public function reactivate(int $customerId, DateTimeImmutable $reactivatedAt): ?SubscriptionActionResult
    {
        return $this->change(
            $customerId,
            static fn (Customer $customer, SubscriptionStatus $paid) =>
                $customer->subscription()->reactivate($paid, $reactivatedAt),
            1,
            true,
        );
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
        $previousSentAt = $subscription->reminderSent();
        if (!$this->customers->trySetReminderSent(
            $subscriptionId,
            $previousSentAt,
            $sentAt,
            $status->id(),
        )) {
            throw new ReminderClaimConflict('Email delivery state changed; reload and try again');
        }

        $subscription->markReminderSent($sentAt);
        try {
            $this->mailer->send($customer);
            return new SubscriptionActionResult(false, true);
        } catch (Throwable $exception) {
            $this->logger->error(
                'Status email resend failed for customer ' . $customerId,
                $exception,
            );
            if (!$this->customers->restoreReminderSent(
                $subscriptionId,
                $sentAt,
                $previousSentAt,
                $status->id(),
            )) {
                throw new ReminderClaimConflict(
                    'Email delivery failed and previous delivery state could not be restored',
                );
            }
            if ($previousSentAt === null) {
                $subscription->clearReminderSent();
            } else {
                $subscription->markReminderSent($previousSentAt);
            }
            return new SubscriptionActionResult(false, false, 'Status email could not be sent');
        }
    }

    private function change(
        int $customerId,
        callable $transition,
        int $targetStatusId,
        bool $sendEmail,
    ): ?SubscriptionActionResult {
        $customer = $this->customers->findById($customerId);
        if ($customer === null) {
            return null;
        }

        $target = $this->statuses->findById($targetStatusId);
        if ($target === null) {
            throw new OutOfBoundsException('Subscription status was not found');
        }

        $subscription = $customer->subscription();
        $expectedStatusId = $subscription->status()->id();
        $expectedChangedAt = $subscription->statusChangedAt();
        if (!$transition($customer, $target)) {
            return new SubscriptionActionResult(false, false);
        }

        $subscriptionId = $subscription->id();
        if ($subscriptionId === null || !$this->customers->trySaveSubscriptionState(
            $subscription,
            $expectedStatusId,
            $expectedChangedAt,
        )) {
            throw new ReminderClaimConflict('Subscription state changed; reload and try again');
        }

        if (!$sendEmail || ($target->subject() === null && $target->message() === null)) {
            return new SubscriptionActionResult(true, false);
        }

        return $this->deliverAfterChange(
            $customer,
            $subscriptionId,
            $target,
            $subscription->statusChangedAt(),
        );
    }

    private function deliverAfterChange(
        Customer $customer,
        int $subscriptionId,
        SubscriptionStatus $target,
        DateTimeImmutable $claimedAt,
    ): SubscriptionActionResult {
        if (!$this->customers->trySetReminderSent(
            $subscriptionId,
            null,
            $claimedAt,
            $target->id(),
        )) {
            return new SubscriptionActionResult(
                true,
                false,
                'State persisted, but another delivery or state update won the race',
            );
        }
        $customer->subscription()->markReminderSent($claimedAt);

        try {
            $this->mailer->send($customer);
            return new SubscriptionActionResult(true, true);
        } catch (Throwable $exception) {
            $this->logger->error(
                'Status email failed after state persisted for customer ' . $customer->id(),
                $exception,
            );
            if (!$this->customers->restoreReminderSent(
                $subscriptionId,
                $claimedAt,
                null,
                $target->id(),
            )) {
                return new SubscriptionActionResult(
                    true,
                    false,
                    'State persisted, but email status could not be restored after delivery failure',
                );
            }
            $customer->subscription()->clearReminderSent();
            return new SubscriptionActionResult(
                true,
                false,
                'State persisted, but status email could not be sent',
            );
        }
    }
}
