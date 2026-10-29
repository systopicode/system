<?php

declare(strict_types=1);
namespace Systopic\System\Media;

/** Supported media types that the public/var web-server allowlist can deliver. */
final class GroupPolicy
{
    /** extension => [label, actual editor capability, accepted detected MIME types] */
    public const TYPES = [
        'jpg'=>['JPG / JPEG','Image editing',['image/jpeg']],
        'png'=>['PNG','Image editing',['image/png']],
        'gif'=>['GIF','Image editing (first frame)',['image/gif']],
        'webp'=>['WebP','Image editing',['image/webp']],
        'svg'=>['SVG','SVG source and preview',['image/svg+xml','image/svg','text/xml','application/xml']],
        'pdf'=>['PDF','Document preview',['application/pdf']],
        'mp4'=>['MP4','Video, poster and formats',['video/mp4','application/mp4']],
        'mp3'=>['MP3','Audio playback',['audio/mpeg','audio/mp3']],
        'm4a'=>['M4A','Audio playback',['audio/mp4','audio/x-m4a','video/mp4']],
        'skp'=>['SketchUp','3D preview',['application/vnd.sketchup.skp','application/x-sketchup','application/octet-stream']],
        'docx'=>['Word (.docx)','Download',['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip']],
        'xlsx'=>['Excel (.xlsx)','Download',['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip']],
        'pptx'=>['PowerPoint (.pptx)','Download',['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip']],
        'txt'=>['Text (.txt)','Download',['text/plain','application/x-empty']],
        'csv'=>['CSV','Download',['text/csv','text/plain','application/csv','application/vnd.ms-excel']],
        'zip'=>['ZIP','Download',['application/zip','application/x-zip-compressed']],
        'vcf'=>['vCard','Download',['text/vcard','text/x-vcard','text/plain']],
    ];

    /** Raster images that the shareImage generator and crop editor can process. */
    public static function shareImageAllowed(string $filename, string $mime): bool
    {
        $ext = self::extension($filename);
        return in_array($ext, ['jpg', 'png', 'gif', 'webp'], true)
            && in_array($mime, self::TYPES[$ext][2], true);
    }

    public static function extension(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match($ext) { 'jpeg'=>'jpg', 'tiff'=>'tif', default=>$ext };
    }

    /** @return list<string> */
    public static function parse(string $value): array
    {
        $out = [];
        foreach (preg_split('/[;,\s]+/', strtolower(trim($value))) ?: [] as $ext) {
            $ext = self::extension('file.' . ltrim($ext, '.'));
            if (isset(self::TYPES[$ext])) $out[] = $ext;
        }
        return array_values(array_unique($out));
    }

    /** @param array<string,array<string,mixed>> $groups @return list<string> */
    public static function allowed(array $groups, string $group): array
    {
        $raw = trim((string) ($groups[$group]['file_extension'] ?? ''));
        return $raw === '' || $raw === '*' ? array_keys(self::TYPES) : self::parse($raw);
    }

    /** @param array<string,array<string,mixed>> $groups */
    public static function error(array $groups, string $group, string $filename): ?string
    {
        $ext = self::extension($filename);
        if (!isset(self::TYPES[$ext])) return "$filename: this file type is not supported for media uploads.";
        if ($groups !== [] && !isset($groups[$group]) && !in_array($group, ['text','unassigned'],true)) return 'That media group no longer exists. Refresh the library.';
        if (!in_array($ext,self::allowed($groups,$group),true)) {
            $label = (string) ($groups[$group]['label'] ?? $group);
            return "$filename is not allowed in $label. Allowed: " . self::description($groups,$group) . '.';
        }
        return null;
    }

    /** Validate detected content before a row or an existing file is changed. */
    public static function contentError(string $path, string $filename): ?string
    {
        $type = self::TYPES[self::extension($filename)] ?? null;
        $mime = is_file($path) ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path) : '';
        if ($type === null || !in_array($mime, $type[2], true)) return "$filename: the file contents do not match the selected file type.";
        $entry = match (self::extension($filename)) {
            'docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml', default => null,
        };
        if ($entry !== null) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) return "$filename: this is not a valid Office document.";
            $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($entry) !== false;
            $zip->close();
            if (!$valid) return "$filename: this is not a valid Office document of the selected type.";
        }
        return null;
    }

    public static function storageName(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME) . '.' . self::extension($filename);
    }

    public static function accept(array $groups, string $group): string
    {
        $extensions = self::allowed($groups,$group);
        if (in_array('jpg',$extensions,true)) $extensions[]='jpeg';
        if (in_array('tif',$extensions,true)) $extensions[]='tiff';
        return implode(',',array_map(static fn($ext)=>'.'.$ext,$extensions));
    }

    public static function description(array $groups, string $group): string
    {
        return implode(', ',array_map(static fn($ext)=>self::TYPES[$ext][0],self::allowed($groups,$group)));
    }
}
