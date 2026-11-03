<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Auth\AuthConfig;
use Systopic\System\Auth\Session;
use Systopic\System\Panels\PanelNode;

/**
 * One widget per group, the groups of one file shown together. Leaving a
 * field writes that value.
 */
final class EnvironmentForm
{
    public static function render(): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<div class="projectFiles">';
        echo '<h1>Environment</h1>';
        echo '<p class="tagsHint">Connection settings affect access to this installation. A new database password takes effect on the next request.</p>';
        echo \message::flush();
        $files = EnvironmentFile::files();
        if ($files === []) {
            echo '<p>No environment config was found.</p></div>';
            return;
        }
        $selected = $files[0]['file'];
        foreach ($files as $info) {
            if ($info['current'] && $info['file'] !== 'config.local.json') {
                $selected = $info['file'];
                break;
            }
        }
        echo '<label class="field envPick"><span>Config</span><select data-on_change="showEnvironment" name="env">';
        foreach ($files as $info) {
            $mark = $info['file'] === 'config.local.json' ? ' (this machine)' : ($info['current'] ? ' (this server)' : '');
            $on = $info['file'] === $selected ? ' selected' : '';
            echo '<option value="' . $e($info['file']) . '"' . $on . '>' . $e($info['name'] . $mark) . '</option>';
        }
        echo '</select></label>';
        foreach ($files as $info) {
            $data = EnvironmentFile::read($info['file']);
            $hidden = $info['file'] === $selected ? '' : ' hidden';
            echo '<div class="envFile"' . $hidden . ' data-env="' . $e($info['file']) . '">';
            if ($info['file'] === 'config.local.json') {
                echo '<p class="tagsHint">Machine-local overrides, merged over the matching environment config. This file is not a separate server and is not synced. Empty values here do not mean the environment’s values are empty.</p>';
            }
            self::scalars($info['file'], 'Database', 'db', EnvironmentFile::GROUPS['db'], is_array($data['db'] ?? null) ? $data['db'] : []);
            self::scalars($info['file'], 'Debug', 'debug', EnvironmentFile::GROUPS['debug'], is_array($data['debug'] ?? null) ? $data['debug'] : []);
            self::scalars($info['file'], 'Mail', 'mail', EnvironmentFile::GROUPS['mail'], is_array($data['mail'] ?? null) ? $data['mail'] : []);
            self::auth($info['file'], $data);
            self::hub($info['file'], $data, $info['current'] && $info['file'] !== 'config.local.json');
            self::version($info['file'], $data);
            self::matches($info['file'], is_array($data['matches'] ?? null) ? $data['matches'] : []);
            echo '</div>';
        }
        echo '</div>';
    }

    public static function handle(PanelNode $panel): void
    {
        $file = (string) \http::dataset('file');
        if (EnvironmentFile::path($file) === null) {
            \message::error('That config file is not here.');
            return;
        }
        if ($panel->action === 'testMail') {
            $current = false;
            foreach (EnvironmentFile::files() as $info) if ($info['file'] === $file && $info['current'] && $file !== 'config.local.json') $current = true;
            if (!Session::isSuperuser() || !$current) {
                \message::error('Only a superuser can test mail for the active environment.');
                return;
            }
            try {
                $result = \Systopic\System\Mail\TestDelivery::send();
                $text = ($result['ok'] ? 'SMTP accepted the test message' : 'SMTP did not accept the test message') . ' for ' . $result['recipient'] . '. Test routing: ' . ($result['testMode'] ? 'on' : 'off') . '. SMTP acceptance does not confirm inbox delivery.';
                $result['ok'] ? \message::confirm($text) : \message::error($text . ' Check the email log and mail settings.');
            } catch (\Throwable $error) {
                $settings = \Systopic\System\Mail\Delivery::settings();
                $detail = \Systopic\System\Mail\Failure::detail($error->getMessage(), $settings);
                $hint = \Systopic\System\Mail\Failure::hint($detail);
                \message::error('Test mail failed. ' . $hint . ' Server response: ' . htmlspecialchars($detail, ENT_QUOTES));
            }
            $panel->updateView();
            return;
        }
        if (in_array($panel->action, ['connectHub', 'startRelay', 'stopRelay', 'addHubOrigin', 'removeHubOrigin'], true)) {
            self::hubAction($panel, $file);
            return;
        }
        $result = match ($panel->action) {
            'save' => EnvironmentFile::set($file, (string) \http::dataset('group'), (string) \http::dataset('key'), self::posted()),
            'saveMatch' => EnvironmentFile::setMatch($file, (int) \http::dataset('index'), (string) \http::dataset('field'), self::posted()),
            'addMatch' => EnvironmentFile::addMatch($file),
            'removeMatch' => EnvironmentFile::removeMatch($file, (int) \http::dataset('index')),
            'createAuth' => EnvironmentFile::createAuth($file),
            'replaceAuth' => EnvironmentFile::createAuth($file, true),
            'removeAuth' => Session::isSuperuser() ? EnvironmentFile::removeAuth($file) : false,
            default => null,
        };
        if ($result === null || $panel->action === '') {
            return;
        }
        if ($panel->action === 'removeAuth' && !Session::isSuperuser()) {
            \message::error('Only a superuser can delete the sign-in key.');
            return;
        }
        if ($result === false) {
            \message::error('That change was not saved.');
            return;
        }
        if (in_array($panel->action, ['createAuth', 'replaceAuth', 'removeAuth'], true)) {
            $written = EnvironmentFile::read($file);
            $auth = is_array($written['auth'] ?? null) ? $written['auth'] : [];
            AuthConfig::rememberSecret((string) ($auth['secret'] ?? ''));
        }
        \message::confirm(match ($panel->action) {
            'createAuth' => 'Sign-in key created.',
            'replaceAuth' => 'Sign-in key replaced. Codes already sent stop working.',
            'removeAuth' => 'Sign-in key deleted. Codes already sent stop working.',
            default => 'Saved.',
        });
        if ($panel->action !== 'save') {
            $panel->updateView();
        }
    }

    private static function hubAction(PanelNode $panel, string $file): void
    {
        if (!Session::isSuperuser()) {
            \message::error('Only a superuser can change the hub.');
            return;
        }
        $here = false;
        foreach (EnvironmentFile::files() as $info) {
            if ($info['file'] === $file && $info['current'] && $file !== 'config.local.json') {
                $here = true;
            }
        }
        if (!$here) {
            \message::error('That file is not the one this server is using.');
            return;
        }
        if (in_array($panel->action, ['addHubOrigin', 'removeHubOrigin'], true)) {
            $origin = (string) \http::dataset('origin');
            $add = $panel->action === 'addHubOrigin';
            $data = EnvironmentFile::read($file);
            $scheme = self::requestScheme();
            $permitted = $add ? CentrifugoHub::projectOrigins($data['matches'] ?? [], $scheme) : CentrifugoHub::ownedOrigins($file);
            if (!in_array($origin, $permitted, true) || !CentrifugoHub::changeOrigin($file, $origin, $add)) {
                \message::error('The origin permission was not saved. Check the address and hub config permissions.');
            } else {
                \message::confirm('Hub config saved. A server administrator must restart the shared hub to apply origin changes. No server was restarted.');
            }
            $panel->updateView();
            return;
        }
        if (in_array($panel->action, ['startRelay', 'stopRelay'], true)) {
            $enabled = $panel->action === 'startRelay';
            EnvironmentFile::enableRelay($file, $enabled)
                ? \message::confirm($enabled ? 'Live updates started for this environment. Other open windows follow on their next request.' : 'Live updates stopped for this environment. Other open windows follow on their next request.')
                : \message::error('Live updates could not be changed.');
            $panel->updateView();
            return;
        }
        $block = CentrifugoHub::relayBlock(!str_starts_with($file, 'LIVE'));
        if ($block === null || !EnvironmentFile::connectRelay($file, $block)) {
            \message::error('The hub key was not saved.');
            return;
        }
        \message::confirm(class_exists(\Systopic\Relay\Relay::class)
            ? 'Hub key saved. Live status starts on the next request.'
            : 'Hub key saved.');
        $panel->updateView();
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function hub(string $file, array $data, bool $here): void
    {
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $relay = is_array($data['relay'] ?? null) ? $data['relay'] : [];
        $ready = ($relay['api'] ?? '') !== '' && ($relay['apiKey'] ?? '') !== '' && strlen((string) ($relay['secret'] ?? '')) >= 16;
        $status = CentrifugoHub::status();
        $included = class_exists(\Systopic\Relay\Relay::class);
        $enabled = $ready && ($relay['enabled'] ?? true) !== false;
        echo '<section class="cmsBox envHub"><header><div class="cmsBoxTitles"><h2>Centrifugo</h2><p class="cmsBoxLead">Live updates and online status for this project.</p></div></header><div class="cmsBoxBody">';
        echo '<div class="envHubStatus"><div><strong>Project connection</strong><span>' . (!$included ? 'Relay package missing' : (!$ready ? 'Not configured' : ($enabled ? 'Enabled' : 'Stopped'))) . '</span></div>';
        if ($here) echo '<div><strong>Shared hub</strong><span>' . ($status['running'] ? 'Running' : 'Not reachable') . '</span></div>';
        echo '</div>';
        if ($here && Session::isSuperuser() && $included) {
            echo '<div class="cmsBoxActions is-start">';
            if ($ready) {
                echo '<button type="button" class="button" data-on_click="' . ($enabled ? 'stopRelay' : 'startRelay') . '" data-file="' . $e($file) . '"><i class="cmsIcon ' . ($enabled ? 'cmsIcon-stop' : 'cmsIcon-play') . '"></i>' . ($enabled ? 'Stop live updates' : 'Start live updates') . '</button>';
            } elseif ($status['configured']) {
                echo '<button type="button" class="button" data-on_click="userConfirm" data-action="connectHub" data-confirm="Copy the local hub key into this environment?" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-key-skeleton"></i>Connect project</button>';
            }
            echo '</div>';
        }
        echo '<p class="cmsBoxLead">Start/Stop affects this project and environment from the next request. The shared server and other projects keep running. Keys stay hidden.</p>';
        if ($here) self::hubOrigins($file, $data);
        echo '</div></section>';
    }

    private static function requestScheme(): string
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    /** @param array<string, mixed> $data */
    private static function hubOrigins(string $file, array $data): void
    {
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $required = CentrifugoHub::projectOrigins(is_array($data['matches'] ?? null) ? $data['matches'] : [], self::requestScheme());
        $owned = CentrifugoHub::ownedOrigins($file);
        $origins = array_values(array_unique([...$required, ...$owned]));
        $allowed = CentrifugoHub::allowedOrigins();
        $invalidIp = false;
        foreach ($data['matches'] ?? [] as $rule) {
            $ip = is_array($rule) ? trim((string) ($rule['serverIp'] ?? '')) : '';
            if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) $invalidIp = true;
        }
        echo '<div class="envHubOrigins"><div class="envHubAddressHeader"><h3>Browser addresses</h3>';
        if (Session::isSuperuser()) echo '<button type="button" class="button" data-on_click="addAddress" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-plus"></i>Add domain / IP</button>';
        echo '</div><p class="cmsBoxLead">Browser origins must be allowed by the shared live-update server. The addresses below come from “Where this config applies”. Shared permissions already exist in the hub configuration; newly added permissions belong to this project.</p>';
        if ($invalidIp) echo '<p class="envHubInvalid">An IP address below is invalid. Correct the marked field before adding its hub permission.</p>';
        echo '<ul class="envOrigins">';
        foreach ($origins as $origin) {
            $known = $allowed === null ? null : CentrifugoHub::originAllowed($origin, $allowed);
            $canRemove = in_array($origin, $owned, true);
            echo '<li><div><code>' . $e($origin) . '</code><small>' . ($known === null ? 'Hub config unavailable' : ($known ? ($canRemove ? 'Added for this project' : 'Shared permission') : 'Missing in saved hub config')) . '</small></div>';
            if (Session::isSuperuser() && $allowed !== null) {
                if (!$known && in_array($origin, $required, true)) {
                    echo '<button type="button" class="button" data-on_click="userConfirm" data-action="addHubOrigin" data-file="' . $e($file) . '" data-origin="' . $e($origin) . '" data-confirm="Add this browser origin to the shared hub config? Applying it requires an administrator to restart the hub." title="Add permission"><i class="cmsIcon cmsIcon-plus"></i>Add</button>';
                } elseif ($canRemove) {
                    echo '<button type="button" class="button transparent" data-on_click="userConfirm" data-action="removeHubOrigin" data-file="' . $e($file) . '" data-origin="' . $e($origin) . '" data-confirm="Remove this project’s origin permission? A permission still registered by another project is kept. Otherwise all uses of this managed origin end after the administrator restarts the hub." title="Remove project permission"><i class="cmsIcon cmsIcon-link-slash"></i></button>';
                }
            }
            echo '</li>';
        }
        if ($origins === []) echo '<li>No browser addresses configured yet.</li>';
        echo '</ul><p class="cmsBoxLead envHubRestart"><i class="cmsIcon cmsIcon-circle-info"></i>Permissions shown are the saved configuration. Origin changes need an administrator to restart the shared hub; these controls never restart it.</p></div>';
    }

    /** @param array<string, mixed> $data */
    private static function auth(string $file, array $data): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $auth = is_array($data['auth'] ?? null) ? $data['auth'] : [];
        $ready = strlen((string) ($auth['secret'] ?? '')) >= 32;
        echo '<section class="cmsBox envSignIn"><header><div class="cmsBoxTitles"><h2>Sign-in</h2>';
        if (!$ready) {
            echo '<p class="cmsBoxLead">A sign-in key enables one-time codes for this environment. Device and screen lifetimes control how long that access remains valid.</p></div></header>';
            echo '<div class="cmsBoxActions is-start">';
            echo '<button type="button" class="button" data-on_click="createAuth" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-key-skeleton"></i> Create sign-in key</button>';
            echo '</div></section>';
            return;
        }
        echo '<p class="cmsBoxLead">A sign-in key is set. It is not shown. Empty screen days means a screen stays until it is revoked.</p></div></header>';
        echo '<div class="cmsBoxBody"><div class="tableScroll"><table class="scalars"><tbody>';
        echo '<tr><td><span>Device days<small>deviceDays</small></span></td><td>';
        self::control($file, 'auth', 'deviceDays', 'int', $auth['deviceDays'] ?? \Systopic\System\Auth\AuthConfig::DEVICE_DAYS);
        echo '</td></tr>';
        echo '<tr><td><span>Screen days<small>screenDays</small></span></td><td>';
        self::control($file, 'auth', 'screenDays', 'string', $auth['screenDays'] ?? '');
        echo '</td></tr>';
        echo '</tbody></table></div>';
        echo '<p class="cmsBoxLead">Replacing the key makes codes already sent stop working.</p>';
        echo '<button type="button" class="button" data-on_click="userConfirm" data-action="replaceAuth" data-confirm="Replace the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-arrows-rotate"></i> Replace sign-in key</button>';
        if (Session::isSuperuser()) {
            echo '<button type="button" class="button" data-on_click="userConfirm" data-action="removeAuth" data-confirm="Delete the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-trash"></i> Delete sign-in key</button>';
        }
        echo '</div></section>';
    }

    /** @param array<string, string> $schema @param array<string, mixed> $values */
    private static function scalars(string $file, string $title, string $group, array $schema, array $values): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<section class="widget"><header><h2>' . $e($title) . '</h2></header>';
        if ($group === 'mail') {
            echo '<main><p class="tagsHint">Test mode redirects all To, CC and BCC recipients to the test recipient. DEV always uses test routing, even when this switch is off. Enable the switch to use test routing in LIVE or STAGING. Leave the recipient empty to use testmail@systopic.media. Invalid test recipients block delivery.</p></main>';
        }
        echo '<div class="tableScroll"><table class="scalars"><tbody>';
        foreach ($schema as $key => $kind) {
            $value = $values[$key] ?? ($kind === 'bool' ? false : '');
            echo '<tr' . ($group === 'debug' ? ' data-debug-option="' . $e($key) . '"' : '') . '><td><span>' . $e($group === 'debug' && $key === 'displayErrors' ? 'Display PHP errors' : self::label($key)) . '<small>' . $e($key) . '</small></span></td><td' . ($kind === 'bool' ? ' data-table-inline' : '') . '>';
            self::control($file, $group, $key, $kind, $value);
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
        if ($group === 'mail' && Session::isSuperuser()) {
            foreach (EnvironmentFile::files() as $info) if ($info['file'] === $file && $info['current'] && $file !== 'config.local.json') {
                $effective = \Systopic\System\Mail\Delivery::settings();
                $transport = trim((string) ($effective['host'] ?? '')) ?: 'Local mail catcher (127.0.0.1:1025)';
                try { $recipient = \Systopic\System\Mail\Delivery::testRecipient(); }
                catch (\RuntimeException) { $recipient = 'Invalid recipient — delivery blocked'; }
                echo '<main><p class="tagsHint">Effective delivery: <strong>' . $e($transport) . '</strong> → <strong>' . $e($recipient) . '</strong>. Local overrides apply. SMTP acceptance confirms handover to this server, not delivery to the inbox.</p></main>';
                echo '<footer><button type="button" class="button" data-action-feedback data-loading data-on_click="testMail" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-paper-plane"></i>Send test email</button><a class="button outline" href="' . $e(\http::$root . 'cms/tools/logs/email/') . '"><i class="cmsIcon cmsIcon-envelope"></i>Email log</a></footer>';
            }
        }
        echo '</section>';
    }

    /** @param array<string, mixed> $data */
    private static function version(string $file, array $data): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<section class="widget"><header><h2>Version</h2></header>';
        echo '<div class="tableScroll"><table class="scalars"><tbody>';
        foreach (EnvironmentFile::SCALARS as $key) {
            echo '<tr><td><span>' . $e(self::label($key)) . '<small>' . $e($key) . '</small></span></td><td>';
            self::control($file, '', $key, 'string', (string) ($data[$key] ?? ''));
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    /** @param list<mixed> $rules */
    private static function matches(string $file, array $rules): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<section class="widget"><header><h2>Where this config applies</h2>';
        echo '<button type="button" class="button" data-on_click="addMatch" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-plus"></i>Add</button></header>';
        echo '<div class="tableScroll"><table class="projectRows"><thead><tr>';
        foreach (EnvironmentFile::MATCH as $field) {
            echo '<th>' . $e(self::label($field)) . '</th>';
        }
        echo '<th></th></tr></thead><tbody>';
        if ($rules === []) {
            echo '<tr><td colspan="5">Nothing here yet.</td></tr>';
        }
        foreach (array_values($rules) as $index => $rule) {
            if (!is_array($rule)) {
                continue;
            }
            echo '<tr>';
            foreach (EnvironmentFile::MATCH as $field) {
                $attrs = ' aria-label="' . $e(self::label($field) . ': rule ' . ($index + 1)) . '" data-file="' . $e($file) . '" data-index="' . (int) $index . '" data-field="' . $e($field) . '"';
                $validation = $field === 'serverIp' ? ' pattern="(?:(?:(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])|(?:[0-9a-fA-F]*:){2,}[0-9a-fA-F:.]*)" title="Enter a valid IPv4 or IPv6 address, or leave empty."' : '';
                if ($field === 'serverIp' && trim((string) ($rule[$field] ?? '')) !== '' && !filter_var(trim((string) $rule[$field]), FILTER_VALIDATE_IP)) $validation .= ' aria-invalid="true"';
                echo '<td><input data-on_blur="saveMatch"' . $attrs . $validation . ' name="value" value="' . $e((string) ($rule[$field] ?? '')) . '" autocomplete="off"></td>';
            }
            echo '<td class="projectRemove"><button type="button" class="button transparent" title="Remove" data-on_click="userConfirm" data-action="removeMatch" data-confirm="Remove this address?" data-file="' . $e($file) . '" data-index="' . (int) $index . '"><i class="cmsIcon cmsIcon-trash"></i></button></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private static function control(string $file, string $group, string $key, string $kind, mixed $value): void
    {
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $attrs = ' aria-label="' . $e(self::label($key)) . '" data-file="' . $e($file) . '" data-group="' . $e($group) . '" data-key="' . $e($key) . '" name="value" autocomplete="off" data-1p-ignore data-lpignore="true" data-bwignore="true"';
        if ($kind === 'bool') {
            $checked = $value === true || $value === 1 || $value === '1' ? ' checked' : '';
            echo '<label class="projectSwitch"><input type="checkbox" class="switch" data-on_change="save"' . $attrs . $checked . '><div><div></div></div></label>';
            return;
        }
        if ($group === 'mail' && $key === 'secure') {
            echo '<select data-on_change="save"' . $attrs . '>';
            foreach (['tls', 'ssl', 'none'] as $option) {
                $selected = (string) $value === $option ? ' selected' : '';
                echo '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
            }
            echo '</select>';
            return;
        }
        if ($group === 'mail' && $key === 'testRecipient') $attrs .= ' type="email" placeholder="testmail@systopic.media"';
        echo '<input data-on_blur="save"' . $attrs . ' value="' . $e(is_scalar($value) ? (string) $value : '') . '">';
    }

    private static function posted(): string
    {
        $values = \http::values();
        if (is_object($values)) {
            return (string) ($values->value ?? '');
        }
        if (is_array($values)) {
            return (string) ($values['value'] ?? '');
        }
        return (string) $values;
    }

    private static function label(string $key): string
    {
        return match ($key) {
            'pass' => 'Password',
            'displayErrors' => 'Display errors',
            'versionAppendix' => 'Version appendix',
            'versionDate' => 'Version date',
            'fromName' => 'From name',
            'testMode' => 'Test mode',
            'testRecipient' => 'Test recipient',
            'serverName' => 'Server name',
            'serverIp' => 'Server IP',
            default => ucfirst($key),
        };
    }
}
