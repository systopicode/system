<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\System\Tables\LoginCodes\Operator as Codes;
use Systopic\System\Tables\Users\Model as User;

/**
 * The mail that carries a one-time code: an invitation (24 h) or a way back
 * in (10 min). The code is big, the link only fills the field - in an
 * installed iOS web app the code has to be typed there, a link would open
 * Safari.
 *
 * The template is the project's if it has `panels/auth/emailCode.tpl.php`,
 * else the plain one next to this class. It gets $user, $purpose, $code,
 * $link, $expires (text) and $appName (PROJECT_NAMESPACE). The log keeps no content
 * (emailSmtp::sensitive()).
 */
final class CodeMail
{
    public const SUBJECTS = [
        Codes::INVITE  => 'Deine Einladung',
        Codes::RECOVER => 'Dein Anmelde-Code',
    ];

    public static function send(User $user, string $purpose, string $code): bool
    {
        if ((string) $user->email === '' || !isset(self::SUBJECTS[$purpose])) {
            return false;
        }
        $appName = defined('PROJECT_NAMESPACE') ? (string) PROJECT_NAMESPACE : 'systopic';
        $link = \http::$siteUrl . 'auth/?code=' . rawurlencode($code);
        $expires = self::lifetimeText(Codes::LIFETIMES[$purpose]);
        $template = \fs::toNative((string) \fs::$projectRoot . 'panels/auth/emailCode.tpl.php');
        if (!is_file($template)) {
            $template = __DIR__ . '/CodeMail.tpl.php';
        }
        ob_start();
        include $template;
        $body = (string) ob_get_clean();
        try {
            return (bool) (new \emailSmtp())
                ->sensitive()
                ->To((string) $user->email, $user->fullname ?: $user->name ?: (string) $user->email)
                ->Subject($appName . ' - ' . self::SUBJECTS[$purpose])
                ->Body($body)
                ->send();
        } catch (\Throwable $e) {
            error_log('[auth] code mail to user ' . $user->id . ' failed: ' . $e->getMessage());
            return false;
        }
    }

    /** "10 Minuten", "24 Stunden". */
    public static function lifetimeText(int $seconds): string
    {
        return $seconds >= 3600 ? intdiv($seconds, 3600) . ' Stunden' : intdiv($seconds, 60) . ' Minuten';
    }
}
