<?php

declare(strict_types=1);

namespace App\Application\Reminder;

use App\Domain\Customer\CustomerRepository;
use DateTimeImmutable;
use DateTimeZone;
use OutOfBoundsException;
use Throwable;

final class ReminderService
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly ReminderMailer $mailer,
        private readonly ReminderLogger $logger,
    ) {
    }

    public function process(DateTimeImmutable $now): ReminderResult
    {
        $sent = 0;
        $smtpFailures = 0;
        $resetFailures = 0;
        $claimFailures = 0;

        foreach ($this->customers->all() as $customer) {
            $subscription = $customer->subscription();
            if (!$subscription->isReminderDue($now)) {
                continue;
            }

            $subscriptionId = $subscription->id();
            if ($subscriptionId === null) {
                continue;
            }

            $claimedAt = $now;
            try {
                $claimed = $this->customers->tryClaimReminder($subscriptionId, $claimedAt);
            } catch (Throwable $exception) {
                $claimFailures++;
                $this->logger->error(
                    'Failed to claim reminder for subscription ' . $subscriptionId,
                    $exception,
                );
                continue;
            }

            if (!$claimed) {
                continue;
            }

            $subscription->markReminderSent($claimedAt);

            try {
                $this->mailer->send($customer);
                $sent++;
            } catch (Throwable $exception) {
                $smtpFailures++;
                $this->logger->error(
                    'SMTP reminder failed for customer ' . $customer->id(),
                    $exception,
                );

                try {
                    $cleared = $this->customers->clearReminderSent($subscriptionId, $claimedAt);
                    if (!$cleared) {
                        $resetFailures++;
                        $this->logger->error(
                            'Failed to reset reminder_sent for subscription ' . $subscriptionId
                            . ' after SMTP failure',
                            $exception,
                        );
                    } else {
                        $subscription->clearReminderSent();
                    }
                } catch (Throwable $resetException) {
                    $resetFailures++;
                    $this->logger->error(
                        'Exception while resetting reminder_sent for subscription ' . $subscriptionId,
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
        );
    }

    public function sendManually(
        int $customerId,
        DateTimeImmutable $now,
        bool $authoriseRepeatedReminder = false,
    ): ?DateTimeImmutable
    {
        $customer = $this->customers->findById($customerId);
        if ($customer === null) {
            throw new OutOfBoundsException('Customer not found');
        }

        $subscription = $customer->subscription();
        $subscriptionId = $subscription->id();
        if ($subscriptionId === null) {
            throw new \LogicException('Cannot send a reminder for an unsaved subscription');
        }
        $previousSentAt = $subscription->reminderSent();
        if ($previousSentAt !== null) {
            if (!$authoriseRepeatedReminder) {
                throw new ReminderAuthorizationRequired(
                    'Das Zurücksetzen des Erinnerungsstatus erfordert eine ausdrückliche Autorisierung',
                );
            }
            if (!$this->customers->clearReminderSent($subscriptionId, $previousSentAt)) {
                throw new ReminderClaimConflict('Reminder state changed; reload and try again');
            }
            $subscription->clearReminderSent();
            return null;
        }

        $sentAt = $now->setTimezone(new DateTimeZone('UTC'));
        if (!$this->customers->trySetReminderSent($subscriptionId, null, $sentAt)) {
            throw new ReminderClaimConflict('Reminder state changed; reload and try again');
        }

        $subscription->markReminderSent($sentAt);
        try {
            $this->mailer->send($customer);
        } catch (Throwable $exception) {
            $this->logger->error(
                'Manual SMTP reminder failed for customer ' . $customerId,
                $exception,
            );

            try {
                $restored = $this->customers->restoreReminderSent(
                    $subscriptionId,
                    $sentAt,
                    $previousSentAt,
                );
                if (!$restored) {
                    $this->logger->error(
                        'Failed to restore reminder_sent after manual SMTP failure for subscription '
                        . $subscriptionId,
                        $exception,
                    );
                    throw new ReminderDeliveryFailed(
                        'Reminder failed and previous reminder status could not be restored',
                        $exception,
                    );
                }
            } catch (ReminderDeliveryFailed $resetFailure) {
                throw $resetFailure;
            } catch (Throwable $resetException) {
                $this->logger->error(
                    'Exception while restoring reminder_sent for subscription ' . $subscriptionId,
                    $resetException,
                );
                throw new ReminderDeliveryFailed(
                    'Reminder failed and previous reminder status could not be restored',
                    $resetException,
                );
            }

            if ($previousSentAt === null) {
                $subscription->clearReminderSent();
            } else {
                $subscription->markReminderSent($previousSentAt);
            }

            throw new ReminderDeliveryFailed('Reminder email could not be sent', $exception);
        }

        return $sentAt;
    }
}
