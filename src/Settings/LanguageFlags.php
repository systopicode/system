<?php

declare(strict_types=1);
namespace Systopic\System\Settings;

use Systopic\System\Sys\Packages;

/** Local flag artwork; project uploads override the small shipped subset. */
final class LanguageFlags
{
    public const MAX_BYTES = 131072;
    public const SOURCE = 'https://github.com/lipis/flag-icons/tree/v7.3.2/flags/4x3';
    private const ALIASES = ['en' => 'gb', 'ja' => 'jp', 'sv' => 'se'];

    public static function code(string $value): ?string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z]{2}(?:-[a-z0-9]{1,8})?$/D', $value) ? $value : null;
    }

    public static function href(string $code): string
    {
        $code = self::code($code);
        if ($code === null) return '';
        foreach (array_unique([$code, self::ALIASES[$code] ?? $code]) as $candidate) {
            $file = 'img/flags/' . $candidate . '.svg';
            $href = Packages::panelAssetHref($file);
            if ($href === '') continue;
            $path = Packages::panelAsset($file);
            return $href . (str_contains($href, '?') ? '&' : '?') . 'v=' . substr(hash_file('sha256', \fs::toNative($path)), 0, 12);
        }
        return '';
    }

    public static function html(string $code): string
    {
        $href = self::href($code);
        return $href === '' ? '<span class="languageFlagMissing" aria-hidden="true">—</span>'
            : '<img class="languageFlag" alt="" width="24" height="18" src="' . htmlspecialchars($href, ENT_QUOTES) . '">';
    }

    public static function token(): string
    {
        return $_SESSION['systopic_language_flag_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function checkToken(string $token): bool
    {
        $expected = $_SESSION['systopic_language_flag_csrf'] ?? '';
        return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }

    private static function inputId(string $rowKey): string
    {
        return 'language-flag-file-' . substr(hash('sha256', $rowKey), 0, 16);
    }

    public static function preview(string $code, string $label, string $rowKey): void
    {
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES);
        echo '<button type="button" class="button transparent languageFlagPreview" data-flag-input="' . self::inputId($rowKey) . '" title="Upload SVG flag for ' . $e($label) . '" aria-label="Upload SVG flag for ' . $e($label) . '"' . (self::code($code) === null ? ' disabled' : '') . ' onclick="document.getElementById(this.dataset.flagInput)?.click()">' . self::html($code) . '</button>';
    }

    public static function control(string $code, string $label, string $rowKey = ''): void
    {
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES);
        echo '<div class="languageFlagControl">';
        $code = self::code($code);
        if ($code === null) { echo '</div>'; return; }
        echo '<form class="languageFlagUpload" method="post" enctype="multipart/form-data" action="uploadFlag?code=' . $e($code) . '">';
        echo '<input type="hidden" name="flagToken" value="' . $e(self::token()) . '">';
        echo '<label class="button transparent icononly languageFlagChoose" title="Upload SVG flag for ' . $e($label) . '"><i class="cmsIcon cmsIcon-arrow-up-from-bracket" aria-hidden="true"></i>';
        echo '<input type="file" name="file" id="' . self::inputId($rowKey !== '' ? $rowKey : $code) . '" accept=".svg,image/svg+xml" title="Upload SVG flag for ' . $e($label) . '" aria-label="Upload SVG flag for ' . $e($label) . '" onchange="this.form.requestSubmit()"></label></form>';
        if (is_file(self::path($code))) {
            echo '<form class="languageFlagReset" method="post" action="resetFlag?code=' . $e($code) . '"><input type="hidden" name="flagToken" value="' . $e(self::token()) . '"><button class="button transparent icononly" type="submit" title="Reset flag" aria-label="Remove project flag for ' . $e($label) . '"><i class="cmsIcon cmsIcon-arrow-rotate-left" aria-hidden="true"></i></button></form>';
        }
        echo '</div>';
    }

    public static function store(string $code): ?string
    {
        $upload = \fsUploader::postedFile('file');
        if ($upload === null) return 'Choose an SVG flag.';
        $tmp = (string) ($upload['tmp_name'] ?? '');
        if (!is_file($tmp) || (($upload['uploader'] ?? null) === null && !is_uploaded_file($tmp))) return 'That file was not uploaded.';
        if (strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION)) !== 'svg') return 'Use an SVG file.';
        if (filesize($tmp) > self::MAX_BYTES) return 'Use an SVG no larger than 128 KB.';
        return self::save($code, (string) file_get_contents($tmp)) ? null : 'Use a static SVG without scripts, embedded images or external references (maximum 128 KB).';
    }

    public static function save(string $code, string $raw): bool
    {
        $code = self::code($code);
        $svg = self::cleanSvg($raw);
        if ($code === null || $svg === null) return false;
        $dest = self::path($code);
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return false;
        $tmp = @tempnam($dir, '.flag-');
        if ($tmp === false) return false;
        $ok = @file_put_contents($tmp, $svg) !== false;
        if ($ok) { @chmod($tmp, 0644); $ok = @rename($tmp, $dest); }
        if (!$ok) @unlink($tmp);
        return $ok;
    }

    public static function reset(string $code): bool
    {
        $code = self::code($code);
        return $code !== null && is_file(self::path($code)) && @unlink(self::path($code));
    }

    private static function path(string $code): string
    {
        return \fs::toNative(rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/img/flags/' . $code . '.svg');
    }

    /** Re-serialize a bounded static-vector allowlist, without entity expansion. */
    public static function cleanSvg(string $raw): ?string
    {
        if ($raw === '' || strlen($raw) > self::MAX_BYTES || preg_match('/<!DOCTYPE|<!ENTITY|<\?xml-stylesheet/i', $raw)) return null;
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new \DOMDocument();
            if (!$doc->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS) || $doc->doctype !== null || $doc->documentElement?->localName !== 'svg') return null;
            $tags = ['svg','g','path','rect','circle','ellipse','line','polyline','polygon','defs','clipPath','mask','linearGradient','radialGradient','stop','use','symbol','marker','title','desc'];
            $attrs = ['id','viewBox','width','height','x','y','x1','y1','x2','y2','cx','cy','r','rx','ry','d','points','fill','fill-rule','fill-opacity','stroke','stroke-width','stroke-linecap','stroke-linejoin','stroke-miterlimit','stroke-dasharray','stroke-dashoffset','stroke-opacity','opacity','transform','clip-path','clip-rule','mask','maskUnits','maskContentUnits','clipPathUnits','gradientUnits','gradientTransform','offset','stop-color','stop-opacity','fx','fy','fr','preserveAspectRatio','overflow','version','markerWidth','markerHeight','markerUnits','refX','refY','orient','marker-start','marker-mid','marker-end','style'];
            $css = ['fill','fill-rule','fill-opacity','stroke','stroke-width','stroke-linecap','stroke-linejoin','stroke-miterlimit','stroke-dasharray','stroke-dashoffset','stroke-opacity','opacity','stop-color','stop-opacity','clip-path'];
            $nodes = $doc->getElementsByTagName('*');
            if ($nodes->length > 2500) return null;
            foreach ($nodes as $node) {
                if (!in_array($node->localName, $tags, true) || !in_array($node->namespaceURI, [null, '', 'http://www.w3.org/2000/svg'], true)) return null;
                $depth = 0;
                for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) if (++$depth > 32) return null;
                foreach ($node->attributes as $attr) {
                    if ($attr->namespaceURI === 'http://www.w3.org/2000/xmlns/') continue;
                    if ($attr->localName === 'href') {
                        if (!in_array($attr->namespaceURI, [null, '', 'http://www.w3.org/1999/xlink'], true) || !preg_match('/^#[A-Za-z_][\w:.-]*$/D', $attr->value)) return null;
                        continue;
                    }
                    if ($attr->namespaceURI !== null && $attr->namespaceURI !== '') return null;
                    if (!in_array($attr->name, $attrs, true)) return null;
                    $value = $attr->value;
                    if (preg_match('/[\\\\@<>]|(?:javascript|data|https?|file|expression|var|attr|env)\s*[:(]/i', $value)) return null;
                    $plain = preg_replace('/url\(\s*#[A-Za-z_][\w:.-]*\s*\)/i', 'none', $value);
                    if (stripos($plain, 'url(') !== false) return null;
                    if ($attr->name === 'style') {
                        foreach (explode(';', $plain) as $declaration) {
                            if (trim($declaration) === '') continue;
                            $parts = explode(':', $declaration, 2);
                            if (count($parts) !== 2 || !in_array(trim($parts[0]), $css, true) || !preg_match('/^[A-Za-z0-9#.,()% +_-]+$/D', trim($parts[1]))) return null;
                        }
                    }
                }
            }
            $xpath = new \DOMXPath($doc);
            if ($xpath->query('//processing-instruction()')->length) return null;
            $doc->documentElement->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            return $doc->saveXML($doc->documentElement) . "\n";
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
