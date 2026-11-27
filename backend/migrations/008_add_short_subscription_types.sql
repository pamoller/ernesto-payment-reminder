INSERT INTO subscription_type (name, duration, reminder)
VALUES
    ('Skool Black Friday', '1 DAYS', 'Reminder Text 123'),
    ('Skool Instant', '0 DAYS', 'Reminder Text 123')
ON DUPLICATE KEY UPDATE reminder = VALUES(reminder);
