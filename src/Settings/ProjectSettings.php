<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/**
 * Loads config/global.settings.json into the same constants settings.php
 * used to define. A name that is not on the list below is ignored, so a key
 * in the file cannot define DB_PASS.
 *
 * Media Settings (config/media.settings.json) are gone: ratios, sizes and
 * format names are `packages.system.media`, the media groups
 * `packages.cms.media` in config.json (bin/config.php --import-settings).
 */
final class ProjectSettings
{
    /** @var list<string> */
    private const SCALARS = [
        'CMS_IMPRINT_URL',
        'CMS_PRIVACY_URL',
        'CMS_OPERATOR_DETAILS',
        'CMS_DPO_DETAILS',
        'PUBLIC_PAGE_NAME',
        'PUBLIC_APP_NAME',
        'PUBLIC_PAGE_TITLE',
        'PUBLIC_PAGE_DESCRIPTION',
        'PUBLIC_PAGE_KEYWORDS',
        'PUBLIC_PAGE_AUTHOR',
        'PUBLIC_PAGE_COPYRIGHT',
        'PAGE_TITLE_SEPERATOR',
        'PAGE_IS_PUBLIC',
        'PAGE_ACL',
        'AUTO_LANGUAGE_DETECTION',
        'FRONTEND_EDIT_BAR',
        'META_AUTHOR',
        'META_KEYWORDS',
        'META_DESCRIPTION',
        'META_PAGE_TOPIC',
        'META_ROBOTS',
        'DEFAULT_LANG',
    ];

    public static function scalarAllowed(string $name): bool
    {
        return in_array($name, self::SCALARS, true);
    }

    /**
     * True when at least one settings JSON file is there. The caller then
     * skips settings.php, including when the JSON does not parse.
     */
    public static function boot(): bool
    {
        $root = rtrim(\fs::toNative((string) \fs::$projectRoot), '\\/');
        if ($root === '') {
            return false;
        }
        $page = ProjectFile::existingJson('page');
        if ($page === null) {
            return false;
        }
        $data = self::read($page);
        if ($data !== null) {
            self::apply('page', $data);
        }
        return true;
    }

    /** @param array<string, mixed> $data */
    public static function apply(string $which, array $data): void
    {
        if ($which === 'page') {
            self::applyPage($data);
        }
    }

    /** @param array<string, mixed> $data */
    private static function applyPage(array $data): void
    {
        foreach (['general', 'meta', 'languages', 'legal'] as $section) {
            if (!is_array($data[$section] ?? null)) {
                continue;
            }
            foreach ($data[$section] as $name => $value) {
                if (!is_string($name) || is_array($value)) {
                    continue;
                }
                self::defineScalar($name, $value);
            }
        }
        $languages = $data['languages']['LANGUAGES'] ?? null;
        if (!is_array($languages) || defined('LANGUAGES')) {
            return;
        }
        $clean = [];
        foreach ($languages as $code => $row) {
            if ((!is_string($code) && !is_int($code)) || !is_array($row)) {
                continue;
            }
            $clean[(string) $code] = $row;
        }
        define('LANGUAGES', $clean);
    }

    private static function defineScalar(string $name, mixed $value): void
    {
        if (!self::scalarAllowed($name) || defined($name)) {
            return;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            define($name, $value);
        }
    }

    /** @return array<string, mixed>|null null when the file is not JSON */
    private static function read(string $path): ?array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        try {
            $data = json_decode(ltrim($raw, "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        return is_array($data) ? $data : null;
    }
}
