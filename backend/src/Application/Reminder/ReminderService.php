<?php

declare(strict_types=1);

namespace App\Application\Reminder;

use App\Domain\Customer\CustomerRepository;
use DateTimeImmutable;
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
}
