<?php

declare(strict_types=1);

namespace App\Infrastructure\Reminder;

use App\Application\Reminder\ReminderMailer;
use App\Domain\Customer\Customer;
use PHPMailer\PHPMailer\PHPMailer;

final class PhpmailerReminderMailer implements ReminderMailer
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $from,
        private readonly string $fromName,
        private readonly string $admin,
        private readonly string $encryption,
    ) {
    }

    public function send(Customer $customer): void
    {
        $template = ReminderTemplate::forDuration(
            $customer->subscription()->durationMonths(),
            $customer->name(),
        );

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->Port = $this->port;
        $mail->SMTPAuth = $this->username !== '';
        $mail->Username = $this->username;
        $mail->Password = $this->password;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($this->from, $this->fromName);
        $mail->addAddress($customer->email(), $customer->name());
        if ($this->admin !== '') {
            $mail->addBCC($this->admin);
        }
        $mail->Subject = $template['subject'];
        $mail->Body = $template['body'];
        $mail->AltBody = $template['body'];

        if ($this->encryption !== '') {
            $mail->SMTPSecure = $this->encryption;
        }

        $mail->send();
    }
}
