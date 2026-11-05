<?php
declare(strict_types=1);
namespace Systopic\System\Auth;

/** Frontend branding only follows a validated, remembered frontend destination. */
final class Branding
{
    /** Remember a same-project entry, without trusting external Referer headers. */
    public static function rememberSource(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        $source = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        $site = parse_url((string) \http::$siteUrl);
        if (!is_array($source) || !is_array($site)) return;
        foreach (['scheme', 'host', 'port'] as $part) {
            if (($source[$part] ?? null) !== ($site[$part] ?? null)) return;
        }
        $path = (string) ($source['path'] ?? '');
        $root = rtrim((string) \http::$root, '/') . '/';
        if (!str_starts_with($path, $root)) return;
        $relative = substr($path, strlen($root));
        if (preg_match('~^(?:(admin/)?auth(?:/|$)|admin/user/logout(?:/|$))~', $relative)) return;
        Session::rememberReturn($path . (isset($source['query']) ? '?' . $source['query'] : ''));
    }

    public static function name(): string
    {
        if (!self::frontend()) return \Systopic\System\Sys\WebApp::name();
        $meta = \Systopic\System\Config\Config::current()?->site()?->meta('appName');
        $name = is_string($meta) ? trim(strip_tags($meta)) : '';
        if ($name === '' && defined('PUBLIC_APP_NAME')) $name = trim(strip_tags((string) PUBLIC_APP_NAME));
        return $name !== '' ? $name : (\Systopic\System\Sys\WebApp::publicName() ?: \Systopic\System\Sys\WebApp::name());
    }

    public static function documentTitle(): string
    {
        return self::frontend()
            ? \Systopic\System\Sys\WebApp::stageMark() . self::name()
            : \Systopic\System\Sys\WebApp::documentTitle();
    }

    public static function frontend(): bool
    {
        $target = Session::peekReturn();
        if ($target === null) return false;
        $path = (string) parse_url($target, PHP_URL_PATH);
        $root = rtrim((string) \http::$root, '/') . '/';
        return str_starts_with($path, $root) && !preg_match('~^(?:admin|cms)(?:/|$)~', substr($path, strlen($root)));
    }

    public static function href(string $file): ?string
    {
        if (!self::frontend()) return null;
        $names = match ($file) {
            'img/icon.svg' => ['img/favicons/favicon.svg', 'img/favicon/favicon.svg', 'img/icon.svg'],
            'img/favicons/favicon-32x32.png', 'img/favicons/favicon-16x16.png' => [$file, 'img/favicon/' . basename($file), 'img/favicons/favicon-96x96.png', 'img/favicon/favicon-96x96.png'],
            default => str_starts_with($file, 'img/favicons/') ? [$file, 'img/favicon/' . basename($file)] : [$file],
        };
        foreach ([(string) \fs::$siteRoot, rtrim((string) \fs::$projectRoot, '/\\') . '/public/'] as $root) {
          foreach ($names as $name) {
            $path = rtrim($root, '/\\') . '/' . $name;
            if (is_file(\fs::toNative($path))) {
                return \Systopic\System\Http\Assets::href((string) \httpHelper::filepathToHref(\fs::toInternal($path)), $path);
            }
          }
        }
        return null;
    }
}
