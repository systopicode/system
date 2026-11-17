<?php

declare(strict_types=1);

namespace Systopic\System\Media;

use Systopic\Db\Scope;
use Systopic\System\Queries\MediaCrops\MediaCrops;
use Systopic\System\Tables\Medias\Files;

/**
 * Answers the address of a variant that is not there yet:
 *
 *   var/formats/<id path>/<name>--<variant>.<type>
 *
 * A file that exists is sent by the web server and never comes here. What
 * comes here is made, stored and sent - if it is declared (`Variants`), and
 * if there is a free place:
 *
 * **Only a few pictures are made at a time.** After a backup was played back
 * or the formats were cleared, a page asks for dozens of them at once, and
 * every one is a picture of many megapixels in memory. So there are `SLOTS`
 * places for the whole installation. A request of the page's loader
 * (`?queue=1`) that finds none is told to come back (503, Retry-After) - the
 * loader shows the preview meanwhile and asks for the visible pictures
 * first. Any other request (a crawler, a share image, no script) waits for a
 * place.
 */
final class VariantServer
{
    /** Pictures made at the same time, all visitors together. `MEDIA_RENDER_SLOTS` overrides. */
    public const SLOTS = 3;

    /** How long a request without `?queue` waits for a place before it gives up. */
    private const WAIT_SECONDS = 25;

    /**
     * @return bool false: not the address of a variant, somebody else's to answer
     */
    public static function handle(string $path): bool
    {
        $request = self::read($path);
        if ($request === null) {
            return false;
        }
        $scope = Scope::default();
        $set = MediaCrops::one($scope, $request['mediaId']);
        $media = $set?->media;
        $stem = $media === null ? '' : Files::variantStem((string) $media->filename) . '--';
        $variant = $media !== null && $stem !== '--' && str_starts_with($request['name'], $stem) && VariantRenderer::supports($media->mimetype)
            ? Variants::parse(substr($request['name'], strlen($stem)))
            : null;
        if ($variant === null) {
            self::fail(404, 'No such picture.');
            return true;
        }
        $suffix = substr($request['name'], strlen($stem));
        $file = Files::variantPath((int) $media->id, (string) $media->filename, $suffix, $request['type']);
        $rebuild = isset($_GET['rebuild']);
        if (!$rebuild && is_file($file)) {
            self::send($file, $request['type']);
            return true;
        }
        $original = Files::originalPath($media);
        $master = Files::variantPath((int) $media->id, (string) $media->filename, 'master', 'webp');
        if (!is_file($original) && !is_file($master)) {
            self::fail(404, 'The original of this picture is missing.');
            return true;
        }

        $slot = self::slot(wait: !isset($_GET['queue']));
        if ($slot === null) {
            header('Retry-After: 2');
            self::fail(503, 'Busy making other pictures - try again.');
            return true;
        }
        try {
            // Somebody else may have made it while this request waited.
            if ($rebuild || !is_file($file)) {
                if ($rebuild && is_file($file)) {
                    @unlink($file);
                }
                $renderer = new VariantRenderer($set, $original);
                $file = $renderer->make($variant, $request['type']);
                if ($renderer->sizeChanged) {
                    $scope->save($media);
                }
            }
        } catch (\Throwable $e) {
            self::fail(404, 'This picture could not be made: ' . $e->getMessage());
            return true;
        } finally {
            flock($slot, LOCK_UN);
            fclose($slot);
        }
        self::send($file, $request['type']);
        return true;
    }

    /**
     * An address of the old layout - `var/formats/<format>/<id path>/<file>` -
     * sent on to the picture that named format stands for now. Links out in
     * the world, and pages stored somewhere, keep working.
     *
     * 302, not 301: while the old rendering can still be switched back on, a
     * browser must not remember the move for good.
     *
     * \return bool false: not such an address, or not to be sent on - the old renderer answers
     */
    public static function redirectLegacy(string $path): bool
    {
        if (!Variants::live() || !preg_match('~(?:^|/)var/formats/([A-Za-z0-9_-]+)/(\d{3})/(\d{2,3})/[^/]+$~D', explode('?', $path, 2)[0], $m)) {
            return false;
        }
        $formats = class_exists('mediaFormats') ? \mediaFormats::get() : [];
        // The working copy and the original of the old renderer are not pictures of a page.
        if (in_array($m[1], ['alias', 'original'], true) || !is_array($formats[$m[1]] ?? null)) {
            return false;
        }
        $set = MediaCrops::one(Scope::default(), (int) ($m[2] . $m[3]));
        if ($set === null || !VariantRenderer::supports($set->media->mimetype)) {
            return false;
        }
        $alias = Variants::alias($formats[$m[1]]);
        $url = (new \Systopic\System\Tables\Medias\Picture($set, $alias['ratio']))->variantUrl(Variants::suffix($alias['ratio'], $alias['megapixels']));
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . $url, true, 302);
        return true;
    }

    /**
     * Makes the master of a medium and its smallest size - the preview every
     * picture has from the start, shown wherever a variant is still to be
     * made. Called after an upload and after the master was edited.
     *
     * Never throws: an upload is not undone because its preview failed. It
     * is made on the first request then.
     */
    public static function preview(Scope $scope, int $mediaId): bool
    {
        try {
            $set = MediaCrops::one($scope, $mediaId);
            if ($set === null || !VariantRenderer::supports($set->media->mimetype)) {
                return false;
            }
            $original = Files::originalPath($set->media);
            if (!is_file($original)) {
                return false;
            }
            $renderer = new VariantRenderer($set, $original);
            $renderer->make(['ratio' => null, 'megapixels' => Variants::smallest(), 'original' => false], 'webp');
            if ($renderer->sizeChanged) {
                $scope->save($set->media);
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The medium and the file an address names - null when it is not an
     * address of the new layout (`var/formats/<format>/<id path>/…` is the
     * old one: a name where this one has the first digits).
     *
     * @return array{mediaId: int, name: string, type: string}|null name without the type
     */
    public static function read(string $path): ?array
    {
        $types = implode('|', array_keys(VariantRenderer::TYPES));
        if (!preg_match('~(?:^|/)var/formats/(\d{3})/(\d{2,3})/([^/]+)\.(' . $types . ')$~D', strtok($path, '?') ?: '', $m)) {
            return null;
        }
        $mediaId = (int) ($m[1] . $m[2]);
        return $mediaId > 0 ? ['mediaId' => $mediaId, 'name' => $m[3], 'type' => $m[4]] : null;
    }

    /**
     * A place to make a picture in: an exclusive lock on one of a few files.
     * Null when all are taken and the caller does not wait (or waited too
     * long). The caller unlocks and closes it.
     *
     * @return resource|null
     */
    public static function slot(bool $wait, ?string $dir = null, ?int $slots = null)
    {
        $dir ??= Files::varDir() . 'formats' . DIRECTORY_SEPARATOR . '.slots' . DIRECTORY_SEPARATOR;
        $slots ??= defined('MEDIA_RENDER_SLOTS') ? max(1, (int) constant('MEDIA_RENDER_SLOTS')) : self::SLOTS;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $deadline = microtime(true) + ($wait ? self::WAIT_SECONDS : 0);
        do {
            for ($i = 1; $i <= $slots; $i++) {
                $handle = @fopen($dir . "slot-$i.lock", 'c');
                if ($handle === false) {
                    continue;
                }
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    return $handle;
                }
                fclose($handle);
            }
            if (!$wait) {
                return null;
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);
        return null;
    }

    private static function send(string $file, string $type): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: ' . VariantRenderer::TYPES[$type]);
            header('Content-Length: ' . filesize($file));
            // No caching said here: the web server marks what comes from a script as not to be
            // kept. The next request finds the file and gets it from there, cached for good.
        }
        readfile($file);
    }

    private static function fail(int $status, string $message): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo $message;
    }
}
