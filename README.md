# Ernestos Payment Reminder

Create a Payment Reminder system using PHP and composer.

The Payment Reminder provider runs regularly by cron jobs to check for pending payments and send reminders to customers via email.


## Architecture
````bash
.env
bin
    payment-reminder.php: uses PaymentReminderService to check for pending payments and send reminders to customers via email.
Services
    Application
        PaymentReminderService
        PaymentReminderCommand
    Domain
        Model
            PaymentReminder
            PaymentReminderGateway
            PaymentReminderPolicy
            PaymentReminderPolicyGateway
            SmtpGateway
    Infrastructure
        Smtp
            DefaultSmtpGateway
        Persistence
            GoogleSheetPaymentReminderGateway
                * list: active = true and status <> 'Closed'
                * updateStatus: set status = NextStatus where ID = ?, processed = ActualDate
            SqlitePaymentPolicyGateway
                * search(Type, Started, Status): return ID, NextStatus
    test
````

## DataSheet

````
ID
CustomerID
Name
Firstname
Started
    'Datetime'
Type
    'Skool Yearly'
    'Skool Half Yearly'
    'Skool Test'
Status
    'Started'
    'Reminder Sent'
    'Followed Up 1'
    'Followed Up 2'
    'Closed'
Processed
    'Datetime'
Active
    'Boolean'
`````

## ReminderPolicy
````sql
-- Skool Yearly, Skool Half Yearly, Skool Test
CREATE TABLE product (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL
);

-- Started, Reminder Sent, Followed Up 1, Followed Up 2, Closed
CREATE TABLE payment_reminder_status (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    remind_after_days INT NOT NULL, -- ?
    subject VARCHAR(255) NULL, -- ?
    body TEXT NULL, -- ?
);

-- Product Payment Reminder Policy
CREATE TABLE product_payment_reminder_policy (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INT NOT NULL,
    payment_reminder_status_id INT NOT NULL,
    next_payment_reminder_status_id INT NOT NULL,
    remind_after_days INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES product(id),
    FOREIGN KEY (payment_reminder_status_id) REFERENCES payment_reminder_status(id),
    FOREIGN KEY (next_payment_reminder_status_id) REFERENCES payment_reminder_status(id)
);

SELECT
    `next`.`id`,
    `next`.`subject`,
    `next`.`body`
FROM
    product_payment_reminder_policy `is`
    JOIN product_payment_reminder_policy `next` ON `is`.next_payment_reminder_status_id = `next`.payment_reminder_status_id
WHERE
    `is`.`product_id` = :product_id AND
    `is`.`payment_reminder_status_id` = :payment_reminder_status_id AND
    CURRENT_DATE >= DATE_ADD(:started, INTERVAL `remind_after_days` DAY);

-- check consistency for user and product
CREATE TABLE payment_reminder_log (
    `email` VARCHAR(255) NOT NULL,
    `product_id` INT NOT NULL,
    `payment_reminder_status_id` INT NOT NULL,
    `sent` DATETIME NOT NULL,
    `email_message` VARCHAR(255) NOT NULL,
    FOREIGN KEY (`product_id`) REFERENCES product(id),
    FOREIGN KEY (`payment_reminder_status_id`) REFERENCES payment_reminder_status(id)
);
````