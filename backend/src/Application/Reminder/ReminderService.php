<?php

declare(strict_types=1);

namespace App\Application\Reminder;

use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\SubscriptionStatusRepository;
use App\Domain\Customer\SubscriptionStatus;
use DateTimeImmutable;
use Throwable;

final class ReminderService
{
    private const CLAIM_LIFETIME_MINUTES = 60;

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly ReminderMailer $mailer,
        private readonly ReminderLogger $logger,
        private readonly int $messageLatestSendDays,
        private readonly SubscriptionStatusRepository $statuses,
    ) {
        if ($messageLatestSendDays < 0) {
            throw new \InvalidArgumentException('Latest send days cannot be negative');
        }
    }

    public function process(DateTimeImmutable $now): ReminderResult
    {
        $sent = 0;
        $smtpFailures = 0;
        $resetFailures = 0;
        $claimFailures = 0;
        $latestSendSkipped = 0;
        $transitions = 0;
        $transitionConflicts = 0;
        $deliveryRetries = 0;
        $statusDefinitions = $this->statuses->all();
        $staleBefore = $now->modify('-' . self::CLAIM_LIFETIME_MINUTES . ' minutes');

        foreach ($this->customers->all() as $customer) {
            $subscription = $customer->subscription();
            $subscriptionId = $subscription->id();
            if ($subscriptionId === null) {
                continue;
            }

            $target = $subscription->nextAutomaticStatus($statusDefinitions);
            $transitionAt = $subscription->nextAutomaticTransitionAt($statusDefinitions);
            if ($target !== null && $transitionAt !== null && $now >= $transitionAt) {
                $expectedStatusId = $subscription->status()->id();
                $expectedChangedAt = $subscription->statusChangedAt();
                $subscription->transitionTo($target, $now);
                try {
                    if (!$this->customers->trySaveSubscriptionState(
                        $subscription,
                        $expectedStatusId,
                        $expectedChangedAt,
                    )) {
                        $transitionConflicts++;
                        continue;
                    }
                } catch (Throwable $exception) {
                    $claimFailures++;
                    $this->logger->error(
                        'Failed to persist subscription status transition ' . $subscriptionId,
                        $exception,
                    );
                    continue;
                }
                $transitions++;
            } elseif ($subscription->status()->id() === 1 || $subscription->reminderSent() !== null) {
                continue;
            } else {
                $deliveryRetries++;
            }

            if (!$this->hasEmail($subscription->status())) {
                continue;
            }
            if (!$subscription->canSendAutomaticMessageAt($now, $this->messageLatestSendDays)) {
                $latestSendSkipped++;
                continue;
            }

            $statusId = $subscription->status()->id();
            $claimedAt = $now;
            try {
                if (!$this->customers->claimEmailDelivery(
                    $subscriptionId,
                    $statusId,
                    $claimedAt,
                    $staleBefore,
                )) {
                    continue;
                }
                $this->mailer->send($customer);
                if (!$this->customers->markEmailDelivered(
                    $subscriptionId,
                    $statusId,
                    $claimedAt,
                    $claimedAt,
                )) {
                    $resetFailures++;
                    $this->logger->error(
                        'Email was sent but its delivery state could not be committed for subscription '
                        . $subscriptionId,
                    );
                    continue;
                }
                $subscription->markReminderSent($claimedAt);
                $sent++;
            } catch (Throwable $exception) {
                $smtpFailures++;
                $this->logger->error(
                    'SMTP reminder failed for customer ' . $customer->id(),
                    $exception,
                );

                try {
                    if (!$this->customers->releaseEmailClaim(
                        $subscriptionId,
                        $statusId,
                        $claimedAt,
                    )) {
                        $resetFailures++;
                        $this->logger->error(
                            'Failed to release email claim for subscription ' . $subscriptionId,
                            $exception,
                        );
                    }
                } catch (Throwable $resetException) {
                    $resetFailures++;
                    $this->logger->error(
                        'Exception while releasing email claim for subscription ' . $subscriptionId,
                        $resetException,
                    );
                }
            }
        }

        return new ReminderResult(
            $sent,
            $smtpFailures,
            $resetFailures,
            $claimFailures,
            $latestSendSkipped,
            $transitions,
            $transitionConflicts,
            $deliveryRetries,
        );
    }

    private function hasEmail(SubscriptionStatus $status): bool
    {
        return $status->subject() !== null || $status->message() !== null;
    }
}
