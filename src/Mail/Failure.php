<?php

declare(strict_types=1);
namespace Systopic\System\Mail;

/** Safe diagnostic shared by the mail log and the manually requested probe. */
final class Failure
{
    public static function detail(string $error, array $settings): string
    {
        foreach (['password','pass','username','user'] as $key) {
            $value = (string) ($settings[$key] ?? '');
            if ($value !== '') $error = str_replace([$value, base64_encode($value)], '[redacted]', $error);
        }
        return substr((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $error), 0, 600);
    }

    public static function hint(string $error): string
    {
        $text = strtolower($error);
        if (preg_match('/\b(535|534)\b|expected 235|authentication|auth login/', $text)) return 'The SMTP server rejected authentication. Check the effective username and password, including local overrides.';
        if (preg_match('/tls|ssl|certificate|crypto/', $text)) return 'The encrypted SMTP connection failed. Check the encryption mode, port and server certificate.';
        if (preg_match('/expected 250.*(550|551|552|553|554)|recipient|rcpt/', $text)) return 'The SMTP server rejected a sender or recipient address. Check the configured sender and test recipient.';
        if (preg_match('/no connection|did not answer|timed out|refused|resolve|network/', $text)) return 'The SMTP server could not be reached or did not answer. Check host, port and network access.';
        if (str_contains($text, 'mail block') || str_contains($text, '"mail" block')) return 'No SMTP transport is configured for this environment.';
        return 'The SMTP server did not accept the message. Check its response and the email log.';
    }
}
