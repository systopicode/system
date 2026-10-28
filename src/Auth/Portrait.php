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
    ];

    /** Older photos can still be a gif or an svg. New ones are webp. */
    private const EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg'];

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
        $upload = \fsUploader::postedFile('portrait');
        $tmp = is_array($upload) ? (string) ($upload['tmp_name'] ?? '') : '';
        $staged = is_array($upload) && ($upload['uploader'] ?? null) !== null;
        if ($tmp === '' || !is_file($tmp) || (!$staged && !is_uploaded_file($tmp))) {
            return 'Choose an image first.';
        }
        if (filesize($tmp) > self::MAX_BYTES) {
            return 'The image must be smaller than 8 MB.';
        }
        $mime = self::mime($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            return 'Use a JPEG, PNG or WebP.';
        }
        $dir = self::dir();
        if ($dir === null) {
            return 'The photo folder could not be created.';
        }
        $square = $dir . $userId . '.webp';
        // A WebP is already the format we keep, so it is not encoded again.
        if ($mime === 'image/webp') {
            $saved = copy($tmp, $square);
        } else {
            $binary = file_get_contents($tmp);
            $saved = is_string($binary) && $binary !== '' && self::writeSquare($binary, $square);
        }
        if (!$saved || !is_file($square)) {
            return 'The image could not be saved.';
        }
        self::clearExcept($userId, $square);
        @chmod($square, 0664);
        if ($staged && is_object($upload['uploader']) && method_exists($upload['uploader'], 'reset')) {
            $upload['uploader']->reset();
        }
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

    /** Centre square at the portrait format, as WebP. The upload itself is not kept. */
    private static function writeSquare(string $binary, string $dest): bool
    {
        $size = self::edge();
        if (self::writeSquareMagick($binary, $dest, $size)) {
            return true;
        }
        if (class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick();
                $image->readImageBlob($binary);
                $image->setIteratorIndex(0);
                $image->setImageBackgroundColor(new \ImagickPixel('white'));
                $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $w = $image->getImageWidth();
                $h = $image->getImageHeight();
                $side = min($w, $h);
                $image->cropImage($side, $side, (int) (($w - $side) / 2), (int) (($h - $side) / 2));
                $image->setImagePage(0, 0, 0, 0);
                $image->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(86);
                $image->writeImage($dest);
                $image->clear();
                return is_file($dest);
            } catch (\Throwable) {
            }
        }
        return self::writeSquareGd($binary, $dest, $size);
    }

    /** ImageMagick in its own process, so a bad file cannot take PHP down. */
    private static function writeSquareMagick(string $binary, string $dest, int $size): bool
    {
        if (!class_exists(\shellBinary::class) || !\shellBinary::isAvailable('magick')) {
            return false;
        }
        $binaryPath = \shellBinary::find('magick');
        $tmp = tempnam(sys_get_temp_dir(), 'portrait');
        if ($binaryPath === null || $tmp === false) {
            return false;
        }
        $src = $tmp . '.img';
        if (!rename($tmp, $src) || file_put_contents($src, $binary) === false) {
            @unlink($tmp);
            @unlink($src);
            return false;
        }
        $edge = (string) $size;
        $command = escapeshellarg($binaryPath)
            . ' -background white ' . escapeshellarg($src)
            . ' -flatten -gravity center -resize ' . escapeshellarg($edge . 'x' . $edge . '^')
            . ' -extent ' . escapeshellarg($edge . 'x' . $edge)
            . ' -quality 86 ' . escapeshellarg($dest);
        exec($command . ' 2>&1', $output, $code);
        @unlink($src);
        return $code === 0 && is_file($dest) && filesize($dest) > 0;
    }

    private static function writeSquareGd(string $binary, string $dest, int $size): bool
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
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
        $out = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($out, 255, 255, 255);
        imagefilledrectangle($out, 0, 0, $size, $size, $white);
        imagecopyresampled($out, $crop, 0, 0, 0, 0, $size, $size, $side, $side);
        imagedestroy($crop);
        $ok = imagewebp($out, $dest, 86);
        imagedestroy($out);
        return $ok && is_file($dest);
    }

    /** The portrait format's edge, from the same list the image editor reads. */
    private static function edge(): int
    {
        $format = \mediaFormats::get()['portrait'] ?? [];
        $edge = (int) ($format['width'] ?? 500);
        return $edge > 0 ? $edge : 500;
    }

    private static function mime(string $path): string
    {
        return strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($path));
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
