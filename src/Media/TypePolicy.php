<?php
declare(strict_types=1);
namespace Systopic\System\Media;
use Systopic\System\Tables\Medias\External;

final class TypePolicy
{
    public const FILE_TYPES = ['image','video','audio','pdf','sketchup','file'];

    public static function mayChoose(string $type, bool $superuser): bool
    {
        return External::is($type) || ($superuser && in_array($type, self::FILE_TYPES, true));
    }

    public static function selected(string $mime, string $filename, string $posted, string $previous, bool $superuser): string
    {
        if (self::mayChoose($posted, $superuser)) return $posted;
        if (External::is($previous)) return $previous;
        return self::inferred($mime, $filename);
    }

    /** Infer the normal UI type from actual file contents, never from a posted selector. */
    public static function inferred(string $mime, string $filename): string
    {
        if (str_contains($mime, 'sketchup') || str_ends_with(strtolower($filename), '.skp')) return 'sketchup';
        if ($mime === 'application/pdf') return 'pdf';
        foreach (['image','video','audio'] as $type) if (str_starts_with($mime, $type . '/')) return $type;
        return 'file';
    }
}
