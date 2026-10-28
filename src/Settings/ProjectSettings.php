<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/**
 * Loads config/global.settings.json and config/media.settings.json into the
 * same constants and globals settings.php used to define. A name that is not
 * on the list below is ignored, so a key in the file cannot define DB_PASS.
 */
final class ProjectSettings
{
    /** @var list<string> */
    private const SCALARS = [
        'PUBLIC_PAGE_NAME',
        'PUBLIC_APP_NAME',
        'PUBLIC_PAGE_TITLE',
        'PUBLIC_PAGE_DESCRIPTION',
        'PUBLIC_PAGE_KEYWORDS',
        'PUBLIC_PAGE_AUTHOR',
        'PUBLIC_PAGE_COPYRIGHT',
        'PAGE_TITLE_SEPERATOR',
        'PAGE_IS_PUBLIC',
        'AUTO_LANGUAGE_DETECTION',
        'FRONTEND_EDIT_BAR',
        'META_AUTHOR',
        'META_KEYWORDS',
        'META_DESCRIPTION',
        'META_PAGE_TOPIC',
        'META_ROBOTS',
        'DEFAULT_LANG',
        'JPEG_QUALITY',
        'BLUR_LEVEL',
        'USED_FILTER',
        'BACKGROUND_JPG',
        'BACKGROUND_COLOR',
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
        $media = ProjectFile::existingJson('media');
        if ($page === null && $media === null) {
            return false;
        }
        if ($page !== null) {
            $data = self::read($page);
            if ($data !== null) {
                self::apply('page', $data);
            }
        }
        if ($media !== null) {
            $data = self::read($media);
            if ($data !== null) {
                self::apply('media', $data);
            }
        }
        return true;
    }

    /** @param array<string, mixed> $data */
    public static function apply(string $which, array $data): void
    {
        if ($which === 'page') {
            self::applyPage($data);
            return;
        }
        if ($which === 'media') {
            self::applyMedia($data);
        }
    }

    /** @param array<string, mixed> $data */
    private static function applyPage(array $data): void
    {
        foreach (['general', 'meta', 'languages'] as $section) {
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

    /** @param array<string, mixed> $data */
    private static function applyMedia(array $data): void
    {
        if (is_array($data['images'] ?? null)) {
            foreach ($data['images'] as $name => $value) {
                if (!is_string($name) || is_array($value)) {
                    continue;
                }
                self::defineScalar($name, $value);
            }
        }
        if (is_array($data['formats'] ?? null) && class_exists('mediaFormats')) {
            $formats = [];
            foreach ($data['formats'] as $name => $row) {
                if (is_string($name) && is_array($row)) {
                    $formats[$name] = $row;
                }
            }
            if ($formats !== []) {
                \mediaFormats::add($formats);
            }
        }
        global $image_formats, $media_groups;
        if (class_exists('mediaFormats')) {
            $image_formats = \mediaFormats::get();
        }
        if (is_array($data['groups'] ?? null)) {
            $groups = [];
            foreach ($data['groups'] as $name => $row) {
                if ((is_string($name) || is_int($name)) && is_array($row)) {
                    $groups[(string) $name] = $row;
                }
            }
            $media_groups = $groups;
        }
        if (is_array($data['videoFormats'] ?? null) && !defined('VIDEO_FORMATS')) {
            $formats = [];
            foreach ($data['videoFormats'] as $name => $row) {
                if (is_string($name) && is_array($row)) {
                    $formats[$name] = $row;
                }
            }
            define('VIDEO_FORMATS', $formats);
        }
        if (is_array($data['external'] ?? null) && !defined('EXTERNAL_MEDIAS')) {
            $names = [];
            foreach ($data['external'] as $name) {
                if (is_string($name) && $name !== '') {
                    $names[] = $name;
                }
            }
            define('EXTERNAL_MEDIAS', $names);
        }
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
