# Ernestos Payment Reminder

Create a Payment Reminder system as a state machine using: 

* PHP
* composer
* Doctrine (MySQL)
* GoogleSheet as external data source

The Payment Reminder itself is a state machine that:
* runs regularly by cron jobs 
* read status and customer information from an external data source
* send pending reminders by email to customers
* log sent email
* update external data source with new status

## Architecture
````bash
.env
crontab
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

## External Data Source Model

````
ID
Name
    'String
Firstname
    'String'
Email
    'Email'
Started
    'Datetime'
Produkt
    'Skool Yearly'
    'Skool Half Yearly'
    'Skool Test'
Reminder Status
    'Started'
    'Reminder Sent'
    'Followed Up 1'
    'Followed Up 2'
    'Closed'
Last Status Change By Service
    'Datetime'
Last Status Set By Service
    'String'
Active
    'Ja'
    'Nein'
````

## Data Model
````sql
DELIMITER $$

-- Skool Yearly, Skool Half Yearly, Skool Test
CREATE TABLE product (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL
)
$$

-- Started, Reminder Sent, Followed Up 1, Followed Up 2, Closed
CREATE TABLE payment_reminder_status (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    remind_after_days INT NOT NULL, -- ?
    subject VARCHAR(255) NOT NULL, -- ?
    body TEXT NOT NULL, -- ?
)
$$

-- Product Payment Reminder Policy
CREATE TABLE product_payment_reminder_policy (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INT NOT NULL,
    payment_reminder_status_id INT NOT NULL,
    next_payment_reminder_status_id INT NOT NULL,
    remind_after_days NULL,
    subject VARCHAR(255) NULL,
    body TEXT NULL,
    UNIQUE KEY (product_id, payment_reminder_status_id),
    UNIQUE KEY (product_id, next_payment_reminder_status_id),
    FOREIGN KEY (product_id) REFERENCES product(id),
    FOREIGN KEY (payment_reminder_status_id) REFERENCES payment_reminder_status(id),
    FOREIGN KEY (next_payment_reminder_status_id) REFERENCES payment_reminder_status(id)
)
$$

-- check consistency for user and product and datasheet
CREATE TABLE payment_reminder_log (
    `email` VARCHAR(255) NOT NULL,
    `product_id` INT NOT NULL,
    `payment_reminder_status_id` INT NOT NULL,
    `sent` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `email_message` VARCHAR(255) NOT NULL,
    FOREIGN KEY (`product_id`) REFERENCES product(id),
    FOREIGN KEY (`payment_reminder_status_id`) REFERENCES payment_reminder_status(id)
)
$$

-- resolve next status for product and current status and started date
CREATE PROCEDURE get_next_payment_reminder_status(
    IN product_id INT, 
    IN status VARCHAR(255), 
    IN started DATETIME
)
BEGIN
SELECT
    `next_prs`.`name` AS new_status, -- write back to sheet
    `prs`.`id` AS last_status_id,
    COALESCE(`next_pprp`.`subject`, `prs`.`subject`) AS subject,
    COALESCE(`next_pprp`.`body`, `prs`.`body`) AS body
FROM
    product_payment_reminder_policy `pprp`
    JOIN payment_reminder_status `prs` ON `pprp`.`payment_reminder_status_id` = `prs`.id
    JOIN product_payment_reminder_policy `next_pprp` ON `pprp`.next_payment_reminder_status_id = `next_pprp`.payment_reminder_status_id
    JOIN payment_reminder_status `next_prs` ON `next_pprp`.`payment_reminder_status_id` = `next_prs`.id
WHERE
    `pprp`.`product_id` = :product_id AND
    `prs`.`name` = :status AND
    CURRENT_DATE >= DATE_ADD(:started, INTERVAL `pprp`.`remind_after_days` DAY);
END
$$
````