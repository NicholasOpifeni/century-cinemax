<?php
declare(strict_types=1);

namespace App;

/**
 * There's no mail server, so messages that would be emailed are written to storage/logs/mail.log
 * instead. Staff read password reset links from there. Never write a password here.
 */
final class MailLog
{
    public static function send(string $to, string $subject, string $body): void
    {
        $entry = sprintf(
            "[%s]\nTo: %s\nSubject: %s\n\n%s\n%s\n",
            date('Y-m-d H:i:s P'),
            $to,
            $subject,
            rtrim($body),
            str_repeat('-', 60),
        );
        file_put_contents(ROOT . '/storage/logs/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
