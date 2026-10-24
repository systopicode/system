<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Medias;

use Systopic\Db\Type\Text;

/**
 * A medium that is a YouTube or Vimeo film, not a file on disk.
 *
 * The kind is `media_type` (youtube, vimeo, …). The address is `media_url`.
 * Sites list the providers in EXTERNAL_MEDIAS; without that, YouTube and
 * Vimeo are offered. The public page decides how to show the player
 * (a consent step, an iframe). This class only knows the address.
 */
final class External
{
    /** @return array<string, string> value => label */
    public static function types(): array
    {
        $raw = defined('EXTERNAL_MEDIAS') && is_array(EXTERNAL_MEDIAS) ? EXTERNAL_MEDIAS : ['YouTube', 'Vimeo'];
        $out = [];
        foreach ($raw as $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $out[strtolower($label)] = $label;
            }
        }
        return $out;
    }

    public static function is(string $type): bool
    {
        $type = strtolower(trim($type));
        return $type !== '' && isset(self::types()[$type]);
    }

    public static function label(string $type): string
    {
        $type = strtolower(trim($type));
        return self::types()[$type] ?? ucfirst($type);
    }

    /** Empty, a bare id, or an https URL. Anything else is dropped. */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url) || preg_match('~^\d{6,12}$~', $url)) {
            return $url;
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * @return array{provider:string,id:string,embedSrc:string}|null
     */
    public static function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $raw)) {
            return self::youtube($raw);
        }
        if (preg_match('~^\d{6,12}$~', $raw)) {
            return self::vimeo($raw);
        }
        $url = self::normalizeUrl($raw);
        if ($url === '') {
            return null;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (preg_match('~(^|\.)youtube(?:-nocookie)?\.com$~', $host) || $host === 'youtu.be') {
            $id = '';
            if ($host === 'youtu.be') {
                $id = trim($path, '/');
            } elseif (!empty($query['v'])) {
                $id = (string) $query['v'];
            } elseif (preg_match('~/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~', $path, $m)) {
                $id = $m[1];
            }
            $id = (string) preg_replace('~[^A-Za-z0-9_-].*$~', '', $id);
            return self::youtube($id);
        }
		if (preg_match('~(^|\.)vimeo\.com$~', $host)) {
            $id = '';
            $hash = trim((string) ($query['h'] ?? ''));
            if (preg_match('~/(?:video/|channels/[^/]+/|groups/[^/]+/videos/|album/\d+/video/|ondemand/[^/]+/)?(\d{6,12})~', $path, $m)) {
                $id = $m[1];
            }
            return self::vimeo($id, $hash);
        }
        return null;
    }

    /** The player address when this row is an external film and the URL parses. */
    public static function embedOf(Model $row): ?array
    {
        if (!self::is((string) $row->type)) {
            return null;
        }
        return self::parse((string) $row->url);
    }

    public static function storeUrl(Model $row, string $url): void
    {
        $row->url = new Text(self::normalizeUrl($url));
    }

    /**
     * Type select and the URL field. The URL shows only while an external type is chosen.
     *
     * @param array{type?:string,url?:string,autosave?:bool} $opts
     */
    public static function fieldsHtml(array $opts = []): string
    {
        $current = strtolower(trim((string) ($opts['type'] ?? '')));
        $url = (string) ($opts['url'] ?? '');
        $autosave = !empty($opts['autosave']);
        $types = [
            'image' => 'Image',
            'video' => 'Video',
            'pdf' => 'PDF',
            'file' => 'File',
        ] + self::types();
        if ($current !== '' && !isset($types[$current])) {
            $types = [$current => ucfirst($current)] + $types;
        }
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $typeAttr = $autosave ? ' data-on_change="updateMediaType"' : ' data-doc="type"';
        $urlAttr = $autosave ? ' data-on_input="updateMediaUrl"' : ' data-doc="url"';

        $html = '<div class="inputGroup">';
        $html .= '<select name="type"' . $typeAttr . '>';
        foreach ($types as $value => $label) {
            $sel = $value === $current ? ' selected' : '';
            $ext = self::is($value) ? ' data-external="1"' : '';
            $html .= '<option value="' . $e($value) . '"' . $sel . $ext . '>' . $e($label) . '</option>';
        }
        $html .= '</select><span class="bar"></span><label>File type</label></div>';
        $html .= '<div class="inputGroup mediaUrlField">';
        $html .= '<input spellcheck="off" type="url" name="url" placeholder="https://…" value="' . $e($url) . '"' . $urlAttr . '>';
        $html .= '<span class="bar"></span><label>Media URL</label></div>';
        $html .= '<p class="mediaUrlNote">The file is the poster. The URL is the film on YouTube or Vimeo. Visitors see the poster until they allow the video.</p>';
        return $html;
    }

    /** @return array{provider:string,id:string,embedSrc:string}|null */
    private static function youtube(string $id): ?array
    {
        $id = trim($id);
        if (!preg_match('~^[A-Za-z0-9_-]{11}$~', $id)) {
            return null;
        }
        return [
            'provider' => 'youtube',
            'id' => $id,
            'embedSrc' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?rel=0',
        ];
    }

    /** @return array{provider:string,id:string,embedSrc:string}|null */
    private static function vimeo(string $id, string $hash = ''): ?array
    {
        $id = trim($id);
        if (!preg_match('~^\d{6,12}$~', $id)) {
            return null;
        }
        $src = 'https://player.vimeo.com/video/' . rawurlencode($id) . '?dnt=1';
        $hash = (string) preg_replace('~[^a-zA-Z0-9]~', '', $hash);
        if ($hash !== '') {
            $src .= '&h=' . rawurlencode($hash);
        }
        return [
            'provider' => 'vimeo',
            'id' => $id,
            'embedSrc' => $src,
        ];
    }
}
