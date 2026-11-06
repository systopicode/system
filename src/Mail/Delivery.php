<?php

declare(strict_types=1);
namespace Systopic\System\Mail;
use Systopic\System\Config\Config;

/** One envelope policy for the standard wrapper and direct SMTPMailer callers. */
final class Delivery
{
    public const DEFAULT_HOST = 'mail.fluxo.systems';
    public const DEFAULT_SENDER = 'noreply@fluxo.systems';
    public const DEFAULT_RECIPIENT = 'testmail@fluxo.systems';

    public static function settings(): array
    {
        $mail = class_exists(Config::class) ? (Config::current()?->package('system')['mail'] ?? null) : null;
        return is_array($mail) ? $mail : [];
    }

    public static function defaultPassword(): string
    {
        $password = (string) getenv('FLUXO_SMTP_PASSWORD');
        if ($password !== '' || !class_exists(\fs::class) || empty(\fs::$projectRoot)) {
            return $password;
        }

        $path = \fs::toNative(\fs::$projectRoot . 'config/.fluxo-smtp-password');
        return is_readable($path) ? rtrim((string) file_get_contents($path), "\r\n") : '';
    }

    public static function testMode(): bool
    {
        return (defined('STAGE') && str_starts_with(strtoupper((string) STAGE), 'DEV'))
            || in_array(self::settings()['testMode'] ?? false, [true, 1, '1'], true);
    }

    public static function testRecipient(): string
    {
        $address = trim((string) (self::settings()['testRecipient'] ?? '')) ?: self::DEFAULT_RECIPIENT;
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('mail: invalid test recipient; delivery blocked');
        }
        return $address;
    }

    /** Run immediately before delivery, even when callers assign public arrays. */
    public static function prepare(object $mail): void
    {
        $url = class_exists('http') ? rtrim((string) (\http::$siteUrl ?? ''), '/') : '';
        $separator = defined('PAGE_TITLE_SEPERATOR') ? (string) PAGE_TITLE_SEPERATOR : ' | ';
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $host = preg_replace('/^www\./i', '', $host);
        $suffix = $host !== '' ? ' ' . trim($separator) . ' ' . $host : '';
        $subject = str_replace(["\r", "\n"], '', (string) ($mail->subject ?? 'No subject'));
        if ($suffix !== '' && !str_ends_with($subject, $suffix)) $subject .= $suffix;
        $mail->subject = $subject;
        if (!self::testMode()) return;
        $recipient = self::testRecipient();
        if ($mail->to === [] && $mail->cc === [] && $mail->bcc === []) return;
        $mail->to = [[$recipient, 'Test recipient']];
        $mail->cc = [];
        $mail->bcc = [];
        $mail->reply_to = [[$recipient, 'Test recipient']];
        if (!str_starts_with((string) ($mail->subject ?? ''), 'Test mail // ')) {
            $mail->subject = 'Test mail // ' . ($mail->subject ?? 'No subject');
        }
    }
}
