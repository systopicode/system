<?php

declare(strict_types=1);
namespace Systopic\System\Mail;

/** An explicitly requested probe through the same transport and routing as system mail. */
final class TestDelivery
{
    public static function send(?callable $transport = null): array
    {
        $recipient = Delivery::testRecipient();
        $mail = new \emailSmtp();
        $mail->addTo($recipient);
        $mail->Subject('SMTP connection test');
        $mail->Body(Letter::render('SMTP connection test', '<p>This is a manually requested SMTP test. No action is required.</p>'));
        Delivery::prepare($mail);
        $ok = $transport ? (bool) $transport($mail) : (bool) $mail->Send();
        return ['ok' => $ok, 'recipient' => (string) $mail->to[0][0], 'testMode' => Delivery::testMode()];
    }
}
