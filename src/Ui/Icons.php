<?php

declare(strict_types=1);
namespace Systopic\System\Ui;

/** Provider-independent keys; registry and CSS are generated from the same checked-in mapping. */
final class Icons
{
    private static ?array $registry = null;
    public static function registry(): array
    {
        return self::$registry ??= json_decode((string) file_get_contents(__DIR__ . '/icon-map.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    /** Resolve registered semantic keys or native cmsIcon classes. */
    public static function classes(string $value, bool $duotone = false): string
    {
        $name = '';
        foreach (preg_split('/\s+/', trim($value)) as $token) {
            if (in_array($token, ['cmsIcon-duotone'], true)) { $duotone = true; continue; }
            if (in_array($token, ['cmsIcon'], true)) continue;
            $candidate = preg_replace('/^cmsIcon-/', '', $token);
            if (isset(self::registry()['outline'][$candidate])) $name = $candidate;
        }
        if ($name === '') {
            error_log('Fluxo: unknown icon key (use a registered key from Ui/icon-map.json)');
            $name = 'circle-question';
        }
        return 'cmsIcon cmsIcon-' . $name . ($duotone && isset(self::registry()['duotone'][$name]) ? ' cmsIcon-duotone' : '');
    }
    public static function html(string $key, bool $duotone = false): string
    {
        return '<i class="' . self::classes($key, $duotone) . '" aria-hidden="true"></i>';
    }
}
