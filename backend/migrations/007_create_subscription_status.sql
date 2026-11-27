CREATE TABLE IF NOT EXISTS subscription_status (
    id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    duration VARCHAR(32) NULL DEFAULT NULL,
    subject VARCHAR(255) NULL DEFAULT NULL,
    message TEXT NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subscription_status (id, name, duration, subject, message)
VALUES
    (1, 'Bezahlt', NULL, 'Zahlungsbestätigung', 'Danke für das Abo!'),
    (2, 'Fällig', '0 DAYS', 'Zahlungserinnerung', 'Hallo {{customer_name}},\n\nIhr Abonnement mit einer Laufzeit von {{duration_months}} Monaten ist abgelaufen. Wir laden Sie zu unserem Anschlussangebot ein.'),
    (3, 'Säumig', '7 DAYS', 'Mahnung', 'Hallo {{customer_name}},\n\nIhre Zahlung für Ihr Abonnement ist noch nicht eingegangen. Bitte gleichen Sie den offenen Betrag zeitnah aus.\n\nVielen Dank!'),
    (4, 'Beendet', '7 DAYS', 'Beendigung des Abonnements', 'Hallo {{customer_name}},\n\nIhr Abonnement wurde beendet, da die ausstehende Zahlung trotz Mahnung nicht eingegangen ist.\n\nIhr Ernesto-Team')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    duration = VALUES(duration),
    subject = VALUES(subject),
    message = VALUES(message);

ALTER TABLE subscriptions
    ADD COLUMN subscription_status_id INT NULL AFTER subscription_type_id,
    ADD COLUMN subscription_status_changed DATETIME(6) NULL AFTER subscription_status_id;

UPDATE subscriptions s
JOIN subscription_type st ON st.id = s.subscription_type_id
SET
    s.subscription_status_id = CASE
        WHEN CURRENT_TIMESTAMP >= CASE st.duration
            WHEN '3 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 3 MONTH)
            WHEN '6 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 6 MONTH)
            WHEN '12 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 12 MONTH)
        END THEN 2
        ELSE 1
    END,
    s.subscription_status_changed = CASE
        WHEN CURRENT_TIMESTAMP >= CASE st.duration
            WHEN '3 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 3 MONTH)
            WHEN '6 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 6 MONTH)
            WHEN '12 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 12 MONTH)
        END THEN CASE st.duration
            WHEN '3 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 3 MONTH)
            WHEN '6 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 6 MONTH)
            WHEN '12 MONTHS' THEN DATE_ADD(s.starts_at, INTERVAL 12 MONTH)
        END
        ELSE s.starts_at
    END
WHERE s.subscription_status_id IS NULL;

ALTER TABLE subscriptions
    MODIFY COLUMN subscription_status_id INT NOT NULL,
    MODIFY COLUMN subscription_status_changed DATETIME(6) NOT NULL,
    ADD KEY idx_subscriptions_status (subscription_status_id),
    ADD CONSTRAINT fk_subscriptions_status
        FOREIGN KEY (subscription_status_id) REFERENCES subscription_status(id);
