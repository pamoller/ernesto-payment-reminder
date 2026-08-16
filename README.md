# Ernestos Payment Reminder

Create a payment reminder system using PHP and composer.

The payment provider runs regular by cron jobs to check for pending payments and send reminders to customers via email.


## Architecture
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


## DataSheet
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

## ReminderPolicy
type
daterange
status
next_status
active
    'Boolean'
email_body
    'String'
email_subject
    'String'