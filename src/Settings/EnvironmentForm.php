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
        echo '<p class="tagsHint">Leave a field to save it. A database password takes effect on the next request.</p>';
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
            $mark = $info['current'] ? ' (this server)' : '';
            $on = $info['file'] === $selected ? ' selected' : '';
            echo '<option value="' . $e($info['file']) . '"' . $on . '>' . $e($info['name'] . $mark) . '</option>';
        }
        echo '</select></label>';
        foreach ($files as $info) {
            $data = EnvironmentFile::read($info['file']);
            $hidden = $info['file'] === $selected ? '' : ' hidden';
            echo '<div class="envFile"' . $hidden . ' data-env="' . $e($info['file']) . '">';
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
        if (in_array($panel->action, ['connectHub', 'startHub', 'installHub'], true)) {
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
        if ($panel->action !== 'save' && $panel->action !== 'saveMatch') {
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
        if ($panel->action === 'installHub') {
            CentrifugoHub::install()
                ? \message::confirm('Centrifugo is installed.')
                : \message::error('Centrifugo was not installed.');
            $panel->updateView();
            return;
        }
        if ($panel->action === 'startHub') {
            CentrifugoHub::start()
                ? \message::confirm('The hub is running.')
                : \message::error('The hub was not started.');
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
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $relay = is_array($data['relay'] ?? null) ? $data['relay'] : [];
        $ready = ($relay['api'] ?? '') !== '' && ($relay['apiKey'] ?? '') !== '' && strlen((string) ($relay['secret'] ?? '')) >= 16;
        $status = CentrifugoHub::status();
        echo '<section class="cmsBox"><header><div class="cmsBoxTitles"><h2>Centrifugo</h2>';
        echo '<p class="cmsBoxLead">Centrifugo is the hub on this machine. It shows who has a page open, and whether they are using it. One hub serves every project here. The key is not shown.</p></div></header>';
        echo '<div class="cmsBoxBody">';
        if (!class_exists(\Systopic\Relay\Relay::class)) {
            echo '<p class="cmsBoxLead">This project does not include the relay, so its pages cannot use the hub.</p>';
        }
        if (!$here) {
            echo '<p class="cmsBoxLead">' . ($ready ? 'A hub key is set for this file.' : 'This file has no hub key.') . '</p>';
            echo '</div></section>';
            return;
        }
        if ($ready) {
            echo '<p class="cmsBoxLead">A hub key is set.</p>';
        }
        if (!$status['installed']) {
            echo '<p class="cmsBoxLead">' . (PHP_OS_FAMILY === 'Darwin'
                ? 'Centrifugo is not installed on this machine.'
                : 'Centrifugo is not installed on this server.') . '</p>';
        } elseif (!$status['configured']) {
            echo '<p class="cmsBoxLead">Centrifugo is installed, but this server cannot read its key.' . ($status['running'] ? ' The hub is running.' : '') . '</p>';
        } elseif ($status['running']) {
            echo '<p class="cmsBoxLead">The hub is running.</p>';
        } else {
            echo '<p class="cmsBoxLead">Centrifugo is installed and not running.</p>';
        }
        $buttons = '';
        if (Session::isSuperuser() && CentrifugoHub::canInstall()) {
            $buttons .= '<button type="button" class="button" data-on_click="userConfirm" data-action="installHub" data-confirm="Install Centrifugo on this Mac? It becomes the hub for every project on this machine." data-file="' . $e($file) . '"><i class="fa-regular fa-download"></i> Install hub</button>';
        }
        if (Session::isSuperuser() && $status['installed'] && !$status['running']) {
            $buttons .= '<button type="button" class="button" data-on_click="startHub" data-file="' . $e($file) . '"><i class="fa-regular fa-play"></i> Start hub</button>';
        }
        if (Session::isSuperuser() && !$ready && $status['configured'] && class_exists(\Systopic\Relay\Relay::class)) {
            $buttons .= '<button type="button" class="button" data-on_click="userConfirm" data-action="connectHub" data-confirm="Save the hub key into this environment? It is copied from the hub on this machine and is not shown." data-file="' . $e($file) . '"><i class="fa-regular fa-key-skeleton"></i> Create hub key</button>';
        }
        if ($buttons !== '') {
            echo '<div class="cmsBoxActions is-start">' . $buttons . '</div>';
        }
        echo '</div></section>';
    }

    /** @param array<string, mixed> $data */
    private static function auth(string $file, array $data): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $auth = is_array($data['auth'] ?? null) ? $data['auth'] : [];
        $ready = strlen((string) ($auth['secret'] ?? '')) >= 32;
        echo '<section class="cmsBox"><header><div class="cmsBoxTitles"><h2>Sign-in</h2>';
        if (!$ready) {
            echo '<p class="cmsBoxLead">One-time codes need a sign-in key. Creating it writes the key, how long a device stays, and how long a screen stays.</p></div></header>';
            echo '<div class="cmsBoxActions is-start">';
            echo '<button type="button" class="button" data-on_click="createAuth" data-file="' . $e($file) . '"><i class="fa-regular fa-key-skeleton"></i> Create sign-in key</button>';
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
        echo '<button type="button" class="button" data-on_click="userConfirm" data-action="replaceAuth" data-confirm="Replace the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="fa-regular fa-rotate-right"></i> Replace sign-in key</button>';
        if (Session::isSuperuser()) {
            echo '<button type="button" class="button" data-on_click="userConfirm" data-action="removeAuth" data-confirm="Delete the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="fa-regular fa-trash"></i> Delete sign-in key</button>';
        }
        echo '</div></section>';
    }

    /** @param array<string, string> $schema @param array<string, mixed> $values */
    private static function scalars(string $file, string $title, string $group, array $schema, array $values): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        echo '<section class="widget"><header><h2>' . $e($title) . '</h2></header>';
        echo '<div class="tableScroll"><table class="scalars"><tbody>';
        foreach ($schema as $key => $kind) {
            $value = $values[$key] ?? ($kind === 'bool' ? false : '');
            echo '<tr><td><span>' . $e(self::label($key)) . '<small>' . $e($key) . '</small></span></td><td>';
            self::control($file, $group, $key, $kind, $value);
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section>';
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
        echo '<button type="button" class="button" data-on_click="addMatch" data-file="' . $e($file) . '"><i class="fa-regular fa-plus"></i>Add</button></header>';
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
                $attrs = ' data-file="' . $e($file) . '" data-index="' . (int) $index . '" data-field="' . $e($field) . '"';
                echo '<td><input data-on_blur="saveMatch"' . $attrs . ' name="value" value="' . $e((string) ($rule[$field] ?? '')) . '" autocomplete="off"></td>';
            }
            echo '<td class="projectRemove"><button type="button" class="button transparent" title="Remove" data-on_click="userConfirm" data-action="removeMatch" data-confirm="Remove this address?" data-file="' . $e($file) . '" data-index="' . (int) $index . '"><i class="fa-regular fa-trash"></i></button></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private static function control(string $file, string $group, string $key, string $kind, mixed $value): void
    {
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $attrs = ' data-file="' . $e($file) . '" data-group="' . $e($group) . '" data-key="' . $e($key) . '" name="value" autocomplete="off"';
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
            'serverName' => 'Server name',
            'serverIp' => 'Server IP',
            default => ucfirst($key),
        };
    }
}
