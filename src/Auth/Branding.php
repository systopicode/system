<?php
declare(strict_types=1);
namespace Systopic\System\Auth;

/** Frontend branding only follows a validated, remembered frontend destination. */
final class Branding
{
    public static function frontend(): bool
    {
        $target = Session::peekReturn();
        if ($target === null) return false;
        $path = (string) parse_url($target, PHP_URL_PATH);
        $root = rtrim((string) \http::$root, '/') . '/';
        return str_starts_with($path, $root) && !preg_match('~^cms(?:/|$)~', substr($path, strlen($root)));
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
