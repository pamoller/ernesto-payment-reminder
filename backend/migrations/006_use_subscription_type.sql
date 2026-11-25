ALTER TABLE subscription_type
    ADD UNIQUE KEY uk_subscription_type_duration (duration);

DELETE FROM customers;

ALTER TABLE subscriptions
    DROP COLUMN duration_months,
    ADD COLUMN subscription_type_id BIGINT UNSIGNED NOT NULL AFTER customer_id,
    ADD KEY idx_subscriptions_subscription_type (subscription_type_id),
    ADD CONSTRAINT fk_subscriptions_subscription_type
        FOREIGN KEY (subscription_type_id) REFERENCES subscription_type(id);
