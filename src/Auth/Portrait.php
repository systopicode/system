<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

/**
 * A user's picture: an uploaded file in `public/var/avatars/`,
 * or a generated portrait whose colours follow the CMS theme
 * (theme.css, then whatever the project overrides).
 */
final class Portrait
{
    private const MAX_BYTES = 8_000_000;

    /** @var array<string, string> */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** @var list<string> */
    private const EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif'];

    /** @var array{ink: string, background: string, paper: string}|null */
    private static ?array $colors = null;

    /**
     * The generated portrait is seeded with the md5 of the email, trimmed
     * and lowercased — the same address draws the same face on every
     * system, and the address itself is not in the URL. No email: the user id.
     */
    public static function src(int $userId, ?string $email): string
    {
        $file = $userId > 0 ? self::file($userId) : null;
        if ($file !== null) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            return \http::$siteUrl . 'var/avatars/' . $userId . '.' . $ext . '?v=' . filemtime($file);
        }
        $colors = self::colors();
        $email = strtolower(trim((string) $email));
        return 'https://api.dicebear.com/10.x/notionists/svg?' . http_build_query([
            'seed' => $email !== '' ? md5($email) : 'user-' . max($userId, 1),
            'backgroundColor' => $colors['background'],
            'inkColor' => $colors['ink'],
            'paperColor' => $colors['paper'],
        ]);
    }

    public static function hasFile(int $userId): bool
    {
        return $userId > 0 && self::file($userId) !== null;
    }

    /** @return string|null an error the screen can show, or null when the file is stored */
    public static function store(int $userId): ?string
    {
        if ($userId <= 0) {
            return 'No user to attach this photo to.';
        }
        $upload = $_FILES['portrait'] ?? null;
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choose an image first.';
        }
        if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return 'The image could not be uploaded.';
        }
        if ((int) ($upload['size'] ?? 0) > self::MAX_BYTES) {
            return 'The image must be smaller than 8 MB.';
        }
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $mime = $tmp !== '' ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        if (!isset(self::ALLOWED[$mime])) {
            return 'Use a JPEG, PNG, WebP or GIF.';
        }
        $binary = file_get_contents($tmp);
        if ($binary === false || $binary === '') {
            return 'The image could not be read.';
        }
        $dir = self::dir();
        if ($dir === null) {
            return 'The photo folder could not be created.';
        }
        $square = $dir . $userId . '.' . (function_exists('imagewebp') ? 'webp' : 'jpg');
        if (!self::writeSquare($binary, $square)) {
            $square = $dir . $userId . '.' . self::ALLOWED[$mime];
            if (file_put_contents($square, $binary) === false) {
                return 'The image could not be saved.';
            }
        }
        self::clearExcept($userId, $square);
        @chmod($square, 0664);
        return null;
    }

    public static function remove(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        self::clearExcept($userId, '');
    }

    private static function file(int $userId): ?string
    {
        $dir = self::dir(false);
        if ($dir === null) {
            return null;
        }
        foreach (self::EXTENSIONS as $ext) {
            $path = $dir . $userId . '.' . $ext;
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    private static function dir(bool $create = true): ?string
    {
        // public/var, the same folder media files use and the site serves.
        $dir = \Systopic\System\Tables\Medias\Files::varDir() . 'avatars' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir) && $create && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        return is_dir($dir) ? $dir : null;
    }

    private static function clearExcept(int $userId, string $keep): void
    {
        $dir = self::dir(false);
        if ($dir === null) {
            return;
        }
        foreach (self::EXTENSIONS as $ext) {
            $path = $dir . $userId . '.' . $ext;
            if ($path !== $keep && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function writeSquare(string $binary, string $dest): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $crop = imagecrop($src, [
            'x' => (int) (($w - $side) / 2),
            'y' => (int) (($h - $side) / 2),
            'width' => $side,
            'height' => $side,
        ]);
        imagedestroy($src);
        if ($crop === false) {
            return false;
        }
        $size = 512;
        $out = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($out, 255, 255, 255);
        imagefilledrectangle($out, 0, 0, $size, $size, $white);
        imagecopyresampled($out, $crop, 0, 0, 0, 0, $size, $size, $side, $side);
        imagedestroy($crop);
        $ok = str_ends_with($dest, '.webp') && function_exists('imagewebp')
            ? imagewebp($out, $dest, 82)
            : imagejpeg($out, $dest, 86);
        imagedestroy($out);
        return $ok && is_file($dest);
    }

    /**
     * Both theme colours: main draws the lines, highlight fills the figure.
     * The backdrop stays a light tint of the page. An override follows.
     *
     * @return array{ink: string, background: string, paper: string}
     */
    private static function colors(): array
    {
        if (self::$colors !== null) {
            return self::$colors;
        }
        $ink = \Systopic\System\Sys\Theme::color('cmsMainColor');
        $accent = \Systopic\System\Sys\Theme::color('cmsHighlightColor');
        $page = \Systopic\System\Sys\Theme::color('cmsBgColor');
        return self::$colors = [
            'ink' => $ink,
            'paper' => $accent,
            'background' => self::mix($accent, $page, 0.28),
        ];
    }

    private static function normalize(string $hex): string
    {
        $hex = strtolower($hex);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return substr($hex, 0, 6);
    }

    private static function mix(string $hex, string $onto, float $amount): string
    {
        $a = self::rgb($hex);
        $b = self::rgb($onto);
        $out = [];
        foreach ([0, 1, 2] as $i) {
            $out[] = (int) round($a[$i] * $amount + $b[$i] * (1 - $amount));
        }
        return sprintf('%02x%02x%02x', $out[0], $out[1], $out[2]);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = self::normalize($hex);
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
