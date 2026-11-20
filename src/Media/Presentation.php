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

    /** The kinds of the file editor (cms) that have a panel of their own - an address below editor/file/. */
    public const EDITOR_PANELS = ['image', 'video', 'svg'];

    /**
     * What the file editor shows a medium in: image, video, svg (each a
     * panel of its own), webm, pdf, audio, sketchup, or file. By the mime
     * type; without one (a list that has only the name) by the ending.
     */
    public static function editorKind(?string $mime, string $filename = '', string $type = ''): string
    {
        $mime = strtolower((string) $mime);
        $ending = GroupPolicy::extension($filename);
        if ($mime === '') {
            $mime = match ($ending) {
                'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
                'svg' => 'image/svg+xml', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf',
                'mp3', 'm4a' => 'audio/mpeg',
                default => '',
            };
        }
        return match (true) {
            in_array($mime, ['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'image/webp'], true) => 'image',
            in_array($mime, ['image/svg', 'image/svg+xml'], true) => 'svg',
            $type === 'sketchup' || str_contains($mime, 'sketchup') || $ending === 'skp' => 'sketchup',
            $mime === 'video/mp4' => 'video',
            $mime === 'video/webm' => 'webm',
            $mime === 'application/pdf' => 'pdf',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };
    }

    /**
     * The address of a medium in the file editor, below the pages panel:
     * `editor/file/svg/?target_id=14` - the kind in it where the kind has a
     * panel (image, video, svg), else `editor/file/?target_id=…`.
     */
    public static function editorPath(int $nodeId, ?string $mime, string $filename = '', string $type = '', string $format = ''): string
    {
        $kind = self::editorKind($mime, $filename, $type);
        return 'editor/file/' . (in_array($kind, self::EDITOR_PANELS, true) ? $kind . '/' : '') . '?target_id=' . $nodeId
            . ($format !== '' ? '&format=' . rawurlencode($format) : '');
    }

    public static function hasThumbnail(string $mime): bool
    {
        return in_array(strtolower($mime), ['image/jpeg','image/jpg','image/png','image/gif','image/webp','image/svg+xml','image/svg','application/pdf','video/mp4'], true);
    }
}
