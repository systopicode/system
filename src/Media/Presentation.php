<?php
declare(strict_types=1);
namespace Systopic\System\Media;

/** Shared file faces for the library, dashboard and upload/replace preview. */
final class Presentation
{
    public const ICONS = [
        'jpg'=>'file-image', 'jpeg'=>'file-image', 'png'=>'file-image', 'gif'=>'file-image', 'webp'=>'file-image', 'svg'=>'vector-polygon', 'pdf'=>'file-pdf', 'mp4'=>'film',
        'zip'=>'file-zipper', 'docx'=>'file-word', 'doc'=>'file-word',
        'xlsx'=>'file-excel', 'xls'=>'file-excel', 'pptx'=>'file-powerpoint',
        'ppt'=>'file-powerpoint', 'csv'=>'file-csv', 'txt'=>'file-lines',
        'vcf'=>'address-card', 'skp'=>'cube', 'mp3'=>'music', 'm4a'=>'music',
    ];

    public static function icon(string $filename): string
    {
        return self::ICONS[GroupPolicy::extension($filename)] ?? 'file';
    }

    public static function opensInline(string $mime): bool
    {
        return preg_match('~^(image|audio|video)/~', $mime) === 1 || $mime === 'application/pdf';
    }

    public static function hasThumbnail(string $mime): bool
    {
        return in_array(strtolower($mime), ['image/jpeg','image/jpg','image/png','image/gif','image/webp','image/svg+xml','image/svg','application/pdf','video/mp4'], true);
    }
}
