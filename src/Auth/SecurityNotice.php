<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\Db\Observer;
use Systopic\Db\Rows;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Passkeys\Model as Passkey;

/** Security notifications after a successful ORM commit, using the shared mail layout. */
final class SecurityNotice implements Observer
{
    private static int $rehashing = 0;
    public const EVENTS = [
        'deviceSignedIn' => 'Your account was signed in on another device',
        'password' => 'Your password was changed',
        'email' => 'Your email address was changed',
        'passkeyAdded' => 'A passkey was added to your account',
        'passkeyRemoved' => 'A passkey was removed from your account',
        'passkeyRenamed' => 'A passkey was renamed in your account',
    ];

    public function read(string $key, Rows $rows): void {}

    public function committed(array $written, array $removed): void
    {
        foreach ($written as $row) {
            $changes = $row->changes();
            if ($row instanceof User) {
                if (isset($changes['passwordHash']) && self::$rehashing === 0 && (string) $changes['passwordHash'][1] !== '') {
                    self::send($row, 'password');
                }
                if (isset($changes['email']) && trim((string) $changes['email'][0]) !== ''
                    && strcasecmp(trim((string) $changes['email'][0]), trim((string) $changes['email'][1])) !== 0) {
                    self::send($row, 'email', ['oldEmail' => (string) $changes['email'][0], 'newEmail' => (string) $changes['email'][1]], [(string) $changes['email'][0], (string) $changes['email'][1]]);
                }
            } elseif ($row instanceof Passkey) {
                if (isset($changes['credentialId']) && $changes['credentialId'][0] === null) {
                    if ($row->user !== null) self::send($row->user, 'passkeyAdded', ['name' => $row->name]);
                } elseif (isset($changes['name']) && $changes['name'][0] !== null && $row->user !== null) {
                    self::send($row->user, 'passkeyRenamed', ['name' => $row->name]);
                }
            }
        }
        $deletedUsers = [];
        foreach ($removed as $row) if ($row instanceof User) $deletedUsers[(int) $row->id] = true;
        foreach ($removed as $row) {
            if ($row instanceof Passkey && !isset($deletedUsers[(int) $row->relationId('user')]) && $row->user !== null) {
                self::send($row->user, 'passkeyRemoved', ['name' => $row->name]);
            }
        }
    }

    /** Replacing an old hash after password verification is not a password change. */
    public static function rehashing(callable $save): mixed
    {
        self::$rehashing++;
        try { return $save(); } finally { self::$rehashing--; }
    }

    /** Called only on the target device, after a successful code sign-in. */
    public static function deviceSignedIn(User $user, \Systopic\System\Tables\SignInCodes\Model $code): void
    {
        try {
            $by = $code->approvedBy ?? $code->createdBy;
            $actor = $by === null ? null : \Systopic\System\Queries\UserLookup\UserLookup::byId(\Systopic\Db\Scope::default(), (int) $by);
            self::send($user, 'deviceSignedIn', [
                'actor' => $by === (int) $user->id ? 'You' : ($actor?->fullname ?: $actor?->name ?: ($by === null ? 'Not recorded' : 'Account #' . $by)),
                'device' => DeviceName::from(DeviceName::requestAgent()),
            ]);
        } catch (\Throwable) {
            // Authentication has succeeded. Mail must never turn it into a failed sign-in.
            error_log('[auth] sign-in notice could not be prepared for user ' . $user->id);
        }
    }

    public static function send(User $user, string $event, array $details = [], ?array $recipients = null): void
    {
        if (!isset(self::EVENTS[$event])) return;
        $addresses = [];
        foreach ($recipients ?? [(string) $user->email] as $address) {
            $address = trim((string) $address);
            if (filter_var($address, FILTER_VALIDATE_EMAIL)) $addresses[strtolower($address)] = $address;
        }
        if ($addresses === []) return;
        try {
            $appName = defined('PROJECT_NAMESPACE') ? (string) PROJECT_NAMESPACE : 'fluxo';
            $title = self::EVENTS[$event];
            $when = gmdate(\Systopic\System\Sys\LocalTime::DATETIME) . ' UTC';
            $template = \fs::toNative((string) \fs::$projectRoot . 'panels/auth/emailSecurity.tpl.php');
            if (!is_file($template)) $template = __DIR__ . '/SecurityNotice.tpl.php';
            ob_start();
            try { include $template; $body = (string) ob_get_contents(); } finally { ob_end_clean(); }
            foreach ($addresses as $address) {
                try {
                    $sent = (new \emailSmtp())->sensitive()->To($address, $user->fullname ?: $user->name ?: $address)
                        ->Subject($appName . ' - ' . $title)->Body($body)->send();
                    if (!$sent) error_log('[auth] security notice delivery failed for user ' . $user->id . ' (' . $event . ')');
                } catch (\Throwable) {
                    error_log('[auth] security notice delivery failed for user ' . $user->id . ' (' . $event . ')');
                }
            }
        } catch (\Throwable) {
            // The change is already committed; a delivery failure must not undo or misreport it.
            error_log('[auth] security notice could not be prepared for user ' . $user->id . ' (' . $event . ')');
        }
    }
}
