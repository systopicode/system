<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/** Project-wide destinations for the CMS's supplementary legal information. */
final class LegalLinks
{
    public const KEYS = ['imprint' => 'CMS_IMPRINT_URL', 'privacy' => 'CMS_PRIVACY_URL'];

    public const DETAILS = ['operator' => 'CMS_OPERATOR_DETAILS', 'dpo' => 'CMS_DPO_DETAILS'];
    public const FIELDS = [...self::KEYS, ...self::DETAILS];

    public static function valid(string $url): bool
    {
        if ($url === '') return true;
        if (preg_match('/[\\\\\x00-\x20\x7f]/', $url)) return false;
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return true;
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null;
    }

    public static function url(string $kind): string
    {
        $value = self::configured($kind);
        if ($value !== '') return $value;
        return rtrim((string) \http::$root, '/') . '/' . $kind . '/';
    }
    /** Empty means no explicit destination; it does not verify page contents. */
    public static function configured(string $kind): string
    {
        $key = self::KEYS[$kind] ?? throw new \InvalidArgumentException('Unknown legal link');
        $value = defined($key) && is_string(constant($key)) ? trim(constant($key)) : '';
        return $value !== '' && self::valid($value) ? $value : '';
    }

    public static function details(string $kind): string
    {
        $key = self::DETAILS[$kind] ?? throw new \InvalidArgumentException('Unknown legal detail');
        return defined($key) && is_string(constant($key)) ? trim(constant($key)) : '';
    }

}
