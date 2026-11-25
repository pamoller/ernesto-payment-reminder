CREATE TABLE IF NOT EXISTS subscription_type (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    duration VARCHAR(32) NOT NULL,
    reminder TEXT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_subscription_type_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subscription_type (name, duration, reminder)
VALUES
    ('Skool Test', '3 MONTHS', 'Reminder Text 123'),
    ('Skool Halbjahres Abo', '6 MONTHS', 'Reminder Text 123'),
    ('Skool Jahres Abo', '12 MONTHS', 'Reminder Text 123')
ON DUPLICATE KEY UPDATE name = VALUES(name);
