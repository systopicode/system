<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

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
        $result = match ($panel->action) {
            'save' => EnvironmentFile::set($file, (string) \http::dataset('group'), (string) \http::dataset('key'), self::posted()),
            'saveMatch' => EnvironmentFile::setMatch($file, (int) \http::dataset('index'), (string) \http::dataset('field'), self::posted()),
            'addMatch' => EnvironmentFile::addMatch($file),
            'removeMatch' => EnvironmentFile::removeMatch($file, (int) \http::dataset('index')),
            'createAuth' => EnvironmentFile::createAuth($file),
            'replaceAuth' => EnvironmentFile::createAuth($file, true),
            default => null,
        };
        if ($result === null || $panel->action === '') {
            return;
        }
        if ($result === false) {
            \message::error('That change was not saved.');
            return;
        }
        \message::confirm(match ($panel->action) {
            'createAuth' => 'Sign-in key created.',
            'replaceAuth' => 'Sign-in key replaced. Codes already sent stop working.',
            default => 'Saved.',
        });
        if ($panel->action !== 'save' && $panel->action !== 'saveMatch') {
            $panel->updateView();
        }
    }

    /** @param array<string, mixed> $data */
    private static function auth(string $file, array $data): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $auth = is_array($data['auth'] ?? null) ? $data['auth'] : [];
        $ready = strlen((string) ($auth['secret'] ?? '')) >= 32;
        echo '<section class="widget"><header><h2>Sign-in</h2></header>';
        echo '<div class="tableScroll">';
        if (!$ready) {
            echo '<p class="tagsHint">One-time codes need a sign-in key. Creating it writes the whole auth block: the key, how long a device stays, and how long a screen stays. The key stays on this machine and is left out of backups.</p>';
            echo '<button type="button" class="button" data-on_click="createAuth" data-file="' . $e($file) . '"><i class="fa-regular fa-key"></i> Create sign-in key</button>';
            echo '</div></section>';
            return;
        }
        echo '<p class="tagsHint">A sign-in key is set. It is not shown and is left out of backups. Empty screen days means a screen stays until it is revoked.</p>';
        echo '<table class="scalars"><tbody>';
        echo '<tr><td><span>Device days<small>deviceDays</small></span></td><td>';
        self::control($file, 'auth', 'deviceDays', 'int', $auth['deviceDays'] ?? \Systopic\System\Auth\AuthConfig::DEVICE_DAYS);
        echo '</td></tr>';
        echo '<tr><td><span>Screen days<small>screenDays</small></span></td><td>';
        self::control($file, 'auth', 'screenDays', 'string', $auth['screenDays'] ?? '');
        echo '</td></tr>';
        echo '</tbody></table>';
        echo '<p class="tagsHint">Replacing the key makes codes already sent stop working.</p>';
        echo '<button type="button" class="button" data-on_click="userConfirm" data-action="replaceAuth" data-confirm="Replace the sign-in key? Codes already sent stop working." data-file="' . $e($file) . '"><i class="fa-regular fa-rotate-right"></i> Replace sign-in key</button>';
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
