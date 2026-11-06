<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Auth\AuthConfig;
use Systopic\System\Auth\Session;
use Systopic\System\Config\Reconcile;
use Systopic\System\Config\Schema;
use Systopic\System\Config\Tree;
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
            self::findings($info['file'], $data);
            self::database($info['file'], $data);
            self::scalars($info['file'], 'Debug', 'debug', 'system', 'debug', $data);
            self::scalars($info['file'], 'Mail', 'mail', 'system', 'mail', $data);
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
            'save' => EnvironmentFile::set($file, (string) \http::dataset('package'), (string) \http::dataset('path'), self::posted()),
            'resetValue' => EnvironmentFile::set($file, (string) \http::dataset('package'), (string) \http::dataset('path'), ''),
            'moveSecrets' => Session::isSuperuser() && EnvironmentFile::moveSecrets($file),
            'moveToInstance' => Session::isSuperuser() && EnvironmentFile::moveToInstance((string) \http::dataset('package'), (string) \http::dataset('path')),
            'resetMail' => Session::isSuperuser() && EnvironmentFile::resetMail($file),
            'saveMatch' => EnvironmentFile::setMatch($file, (int) \http::dataset('index'), (string) \http::dataset('field'), self::posted()),
            'addMatch' => EnvironmentFile::addMatch($file),
            'removeMatch' => EnvironmentFile::removeMatch($file, (int) \http::dataset('index')),
            'createAuth' => EnvironmentFile::here($file) && EnvironmentFile::createAuth($file),
            'replaceAuth' => EnvironmentFile::here($file) && EnvironmentFile::createAuth($file, true),
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
            AuthConfig::rememberSecret(EnvironmentFile::authSecret($file));
        }
        \message::confirm(match ($panel->action) {
            'moveSecrets' => 'The secrets now lie in config.local.json.',
            'resetValue' => 'Reset to the default.',
            'resetMail' => 'Mail settings reset to defaults.',
            'createAuth' => 'Sign-in key created.',
            'replaceAuth' => 'Sign-in key replaced. Codes already sent stop working.',
            'removeAuth' => 'Sign-in key deleted. Codes already sent stop working.',
            default => 'Saved.',
        });
        // a secret leaves its field empty again, and says that it is set
        $field = $panel->action === 'save' ? Schema::field((string) \http::dataset('package'), (string) \http::dataset('path')) : null;
        if ($panel->action !== 'save' || !empty($field[0]['writeOnly'])) {
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
        $relay = EnvironmentFile::effective($file, 'relay');
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
            foreach (is_array($rule) ? (array) ($rule['serverIp'] ?? []) : [] as $ip) {
                if (trim((string) $ip) !== '' && !filter_var(trim((string) $ip), FILTER_VALIDATE_IP)) $invalidIp = true;
            }
        }
        echo '<div class="envHubOrigins"><div class="envHubAddressHeader"><h3>Browser addresses</h3>';
        if (Session::isSuperuser()) echo '<button type="button" class="button" data-on_click="addAddress" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-plus"></i>Add domain / IP</button>';
        echo '</div><p class="cmsBoxLead">Browser origins must be allowed by the shared live-update server. The addresses below come from “Where this config applies”. Shared permissions already exist in the hub configuration; newly added permissions belong to this project.</p>';
        if ($invalidIp) echo '<p class="envHubInvalid">An IP address below is invalid. Correct the marked field before adding its hub permission.</p>';
        echo '<section class="widget is-list envHubTable"><div class="tableScroll"><table class="envOrigins"><thead><tr><th>Browser address</th><th>Permission</th><th></th></tr></thead><tbody>';
        foreach ($origins as $origin) {
            $known = $allowed === null ? null : CentrifugoHub::originAllowed($origin, $allowed);
            $canRemove = in_array($origin, $owned, true);
            echo '<tr><td><code>' . $e($origin) . '</code></td><td><small>' . ($known === null ? 'Hub config unavailable' : ($known ? ($canRemove ? 'Added for this project' : 'Shared permission') : 'Missing in saved hub config')) . '</small></td><td class="icons">';
            if (Session::isSuperuser() && $allowed !== null) {
                if (!$known && in_array($origin, $required, true)) {
                    echo '<button type="button" class="button" data-on_click="userConfirm" data-action="addHubOrigin" data-file="' . $e($file) . '" data-origin="' . $e($origin) . '" data-confirm="Add this browser origin to the shared hub config? Applying it requires an administrator to restart the hub." title="Add permission"><i class="cmsIcon cmsIcon-plus"></i>Add</button>';
                } elseif ($canRemove) {
                    echo '<button type="button" class="button transparent" data-on_click="userConfirm" data-action="removeHubOrigin" data-file="' . $e($file) . '" data-origin="' . $e($origin) . '" data-confirm="Remove this project’s origin permission? A permission still registered by another project is kept. Otherwise all uses of this managed origin end after the administrator restarts the hub." title="Remove project permission"><i class="cmsIcon cmsIcon-link-slash"></i></button>';
                }
            }
            echo '</td></tr>';
        }
        if ($origins === []) echo '<tr><td colspan="3">No browser addresses configured yet.</td></tr>';
        echo '</tbody></table></div></section><p class="cmsBoxLead envHubRestart"><i class="cmsIcon cmsIcon-circle-info"></i>Permissions shown are the saved configuration. Origin changes need an administrator to restart the shared hub; these controls never restart it.</p></div>';
    }

    /** @param array<string, mixed> $data */
    private static function auth(string $file, array $data): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        if (!EnvironmentFile::here($file)) {
            echo '<section class="cmsBox envSignIn"><header><div class="cmsBoxTitles"><h2>Sign-in</h2><p class="cmsBoxLead">The sign-in key lies on that machine, in its config.local.json.</p></div></header>';
            echo '<div class="cmsBoxBody"><div class="tableScroll"><table class="scalars"><tbody>';
            self::rows($file, 'system', 'auth', $data, '', ['deviceDays', 'screenDays']);
            echo '</tbody></table></div></div></section>';
            return;
        }
        $ready = strlen(EnvironmentFile::authSecret($file)) >= 32;
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
        self::rows($file, 'system', 'auth', $data, '', ['deviceDays', 'screenDays']);
        echo '</tbody></table></div>';
        echo '<p class="cmsBoxLead">Replacing the key makes codes already sent stop working.</p>';
        echo '<button type="button" class="button" data-on_click="userConfirm" data-action="replaceAuth" data-confirm="Replace the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-arrows-rotate"></i> Replace sign-in key</button>';
        if (Session::isSuperuser()) {
            echo '<button type="button" class="button" data-on_click="userConfirm" data-action="removeAuth" data-confirm="Delete the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-trash"></i> Delete sign-in key</button>';
        }
        echo '</div></section>';
    }

    /** @param array<string, mixed> $data the file */
    private static function scalars(string $file, string $title, string $group, string $package, string $path, array $data): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<section class="widget"><header><h2>' . $e($title) . '</h2></header>';
        if ($group === 'mail') {
            echo '<main><p class="tagsHint">Test mode redirects all To, CC and BCC recipients to the test recipient. DEV always uses test routing, even when this switch is off. Enable the switch to use test routing in LIVE or STAGING. Leave the recipient empty to use testmail@fluxo.systems. Invalid test recipients block delivery.</p></main>';
        }
        echo '<div class="tableScroll"><table class="scalars"><tbody>';
        self::rows($file, $package, $path, $data, $group);
        echo '</tbody></table></div>';
        if ($group === 'mail' && Session::isSuperuser()) {
            $testActions = '';
            foreach (EnvironmentFile::files() as $info) if ($info['file'] === $file && $info['current'] && $file !== 'config.local.json') {
                $effective = \Systopic\System\Mail\Delivery::settings();
                $transport = trim((string) ($effective['host'] ?? '')) ?: 'Local mail catcher (127.0.0.1:1025)';
                try { $recipient = \Systopic\System\Mail\Delivery::testRecipient(); }
                catch (\RuntimeException) { $recipient = 'Invalid recipient — delivery blocked'; }
                echo '<main><p class="tagsHint">Effective delivery: <strong>' . $e($transport) . '</strong> → <strong>' . $e($recipient) . '</strong>. Local overrides apply. SMTP acceptance confirms handover to this server, not delivery to the inbox.</p></main>';
                $testActions = '<button type="button" class="button" data-action-feedback data-loading data-on_click="testMail" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-paper-plane"></i>Send test email</button><a class="button outline" href="' . $e(\http::$root . 'admin/tools/logs/email/') . '"><i class="cmsIcon cmsIcon-envelope"></i>Email log</a>';
            }
            echo '<footer><button type="button" class="button outline" data-on_click="userConfirm" data-action="resetMail" data-confirm="Reset mail settings for this file? This clears SMTP credentials and test routing overrides." data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i>Reset to defaults</button>' . $testActions . '</footer>';
        }
        echo '</section>';
    }

    /** @param array<string, mixed> $data */
    private static function version(string $file, array $data): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<section class="widget"><header><h2>Version</h2></header>';
        echo '<div class="tableScroll"><table class="scalars"><tbody>';
        self::rows($file, 'system', '', $data);
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
                if ($field === 'serverIp' && !empty($rule[$field]) && array_filter((array) $rule[$field], static fn($ip) => !filter_var(trim((string) $ip), FILTER_VALIDATE_IP)) !== []) $validation .= ' aria-invalid="true"';
                echo '<td><input data-on_blur="saveMatch"' . $attrs . $validation . ' name="value" value="' . $e(implode(', ', (array) ($rule[$field] ?? ''))) . '" autocomplete="off"></td>';
            }
            echo '<td class="projectRemove"><button type="button" class="button transparent" title="Remove" data-on_click="userConfirm" data-action="removeMatch" data-confirm="Remove this address?" data-file="' . $e($file) . '" data-index="' . (int) $index . '"><i class="cmsIcon cmsIcon-trash"></i></button></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    /**
     * What does not fit in the file. What is missing shows in its row.
     *
     * @param array<string, mixed> $data the file
     */
    private static function findings(string $file, array $data): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $here = EnvironmentFile::here($file);
        $lines = [];
        $exposed = [];
        foreach (Reconcile::findings($file, $data) as $finding) {
            $name = trim($finding['package'] . '.' . $finding['path'], '.');
            if ($finding['status'] === Reconcile::EXPOSED) {
                $exposed[] = $name;
            } elseif ($finding['status'] === Reconcile::ORPHAN) {
                $keeps = $file === EnvironmentFile::LOCAL || !$here;
                $lines[] = '<s>' . $e($name) . '</s> is defined nowhere. ' . ($keeps ? 'It stays in this file.' : 'It goes with the next save.');
            } elseif ($finding['status'] === Reconcile::FOREIGN) {
                $lines[] = '<code>' . $e($name) . '</code> is not installed here. Its settings stay.';
            } elseif ($finding['status'] === Reconcile::MISPLACED) {
                $move = $finding['owner'] === 'machine' && $file === EnvironmentFile::LOCAL && Session::isSuperuser()
                    ? ' <button type="button" class="button" data-on_click="moveToInstance" data-file="' . $e($file) . '" data-package="' . $e($finding['package']) . '" data-path="' . $e($finding['path']) . '">Move</button>'
                    : '';
                $lines[] = '<code>' . $e($name) . '</code> belongs into ' . ($finding['owner'] === 'project' ? 'config.json' : ($finding['owner'] === 'local' ? 'config.local.json' : 'the instance file')) . '.' . $move;
            }
        }
        if ($exposed !== []) {
            $lines[] = 'Secrets outside of config.local.json: <code>' . $e(implode(', ', $exposed)) . '</code>. '
                . ($here && Session::isSuperuser()
                    ? '<button type="button" class="button" data-on_click="userConfirm" data-action="moveSecrets" data-confirm="Move the secrets of this file to config.local.json of this machine?" data-file="' . $e($file) . '">Move to this machine</button>'
                    : 'They move on the machine this file belongs to.');
        }
        if ($lines !== []) {
            echo '<section class="widget"><header><h2>Check</h2></header><main><p class="tagsHint">' . implode('</p><p class="tagsHint">', $lines) . '</p></main></section>';
        }
    }

    /**
     * The widget of a database connection: `main`, and whatever else the
     * project connects to.
     *
     * @param array<string, mixed> $data the file
     */
    private static function database(string $file, array $data): void
    {
        $names = array_keys((array) (EnvironmentFile::effective($file, 'db')['connections'] ?? []));
        foreach (array_values(array_unique(['main', ...$names])) as $name) {
            self::scalars($file, $name === 'main' ? 'Database' : 'Database: ' . $name, 'db', 'db', 'connections.' . $name, $data);
        }
    }

    /**
     * The rows of a group, from the definitions: what this file may hold, and
     * the secrets of this machine. A value the file does not have shows what
     * counts instead.
     *
     * @param array<string, mixed> $data the file
     * @param list<string>         $only
     */
    private static function rows(string $file, string $package, string $path, array $data, string $group = '', array $only = []): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        foreach (Schema::children($package, $path) as $key => [$definition, $owner]) {
            $secret = !empty($definition['writeOnly']);
            if (($only !== [] && !in_array($key, $only, true)) || (!$secret && !EnvironmentFile::holds($file, $definition, $owner))) {
                continue;
            }
            $full = $path === '' ? $key : "$path.$key";
            $value = Tree::get($data, ['packages', $package, ...explode('.', $full)]);
            $fallback = $definition['x-stages'][strtoupper(explode('.', $file)[0])] ?? $definition['default'] ?? null;
            $changed = !$secret && $value !== null && $fallback !== null && $value != $fallback;
            $status = !$secret && $value === null ? '<small class="envDefault">not saved</small>' : ($changed ? '<small class="envDefault">≠ default ' . $e(is_bool($fallback) ? ($fallback ? 'on' : 'off') : $fallback) . ' <button type="button" class="button transparent" data-on_click="resetValue" data-file="' . $e($file) . '" data-package="' . $e($package) . '" data-path="' . $e($full) . '">reset</button></small>' : '');
            echo '<tr' . ($group === 'debug' ? ' data-debug-option="' . $e($key) . '"' : '') . '><td><span>' . $e((string) ($definition['title'] ?? self::label($key))) . '<small>' . $e($key) . '</small>' . $status . '</span></td><td>';
            if ($secret) {
                self::secret($file, $package, $full, $definition);
            } else {
                self::control($file, $group, $package, $full, $definition, $value);
            }
            echo '</td></tr>';
        }
    }

    /**
     * Never shown. Where this installation runs it can be replaced, an empty
     * field keeps it. Another machine's secret is not on this one.
     *
     * @param array<string, mixed> $definition
     */
    private static function secret(string $file, string $package, string $path, array $definition): void
    {
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        if (!EnvironmentFile::here($file)) {
            echo '<small>Kept on that machine</small>';
            return;
        }
        $set = (string) Tree::get(EnvironmentFile::effective($file, $package), explode('.', $path)) !== '';
        echo '<input type="password" data-on_blur="save" aria-label="' . $e((string) ($definition['title'] ?? $path)) . '" data-file="' . $e($file) . '" data-package="' . $e($package) . '" data-path="' . $e($path) . '" name="value" autocomplete="new-password" placeholder="' . ($set ? 'set, empty keeps it' : 'not set') . '">';
    }

    /** @param array<string, mixed> $definition */
    private static function control(string $file, string $group, string $package, string $path, array $definition, mixed $value): void
    {
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $key = substr((string) strrchr('.' . $path, '.'), 1);
        $stage = strtoupper(explode('.', $file)[0]);
        $fallback = $definition['x-stages'][$stage] ?? $definition['default'] ?? null;
        $attrs = ' aria-label="' . $e((string) ($definition['title'] ?? self::label($key))) . '" data-file="' . $e($file) . '" data-group="' . $e($group) . '" data-key="' . $e($key) . '" data-package="' . $e($package) . '" data-path="' . $e($path) . '" name="value" autocomplete="off"';
        if (in_array('boolean', (array) ($definition['type'] ?? []), true)) {
            $on = $value ?? $fallback;
            $checked = $on === true || $on === 1 || $on === '1' ? ' checked' : '';
            echo '<label class="projectSwitch"><input type="checkbox" class="switch" data-on_change="save"' . $attrs . $checked . '><div><div></div></div></label>';
            return;
        }
        if (isset($definition['enum'])) {
            echo '<select data-on_change="save"' . $attrs . '>';
            if (!in_array('', $definition['enum'], true)) {
                echo '<option value=""' . ($value === null ? ' selected' : '') . '>' . ($fallback === null ? 'automatic' : 'default: ' . $e($fallback)) . '</option>';
            }
            foreach ($definition['enum'] as $option) {
                echo '<option value="' . $e($option) . '"' . ((string) $value === (string) $option && $value !== null ? ' selected' : '') . '>' . ($option === '' ? 'default' : $e($option)) . '</option>';
            }
            echo '</select>';
            return;
        }
        if (($definition['format'] ?? '') === 'email') {
            $attrs .= ' type="email"';
        }
        if ($fallback !== null || !empty($definition['x-computed'])) {
            $attrs .= ' placeholder="' . ($fallback === null ? 'automatic' : $e($fallback)) . '"';
        }
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
