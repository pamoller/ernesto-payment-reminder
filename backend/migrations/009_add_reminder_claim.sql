ALTER TABLE subscriptions
    ADD COLUMN reminder_claimed_at DATETIME(6) NULL DEFAULT NULL AFTER reminder_sent,
    ADD KEY idx_subscriptions_reminder_claimed (reminder_claimed_at);
