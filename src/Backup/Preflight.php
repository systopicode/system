<?php

declare(strict_types=1);
namespace Systopic\System\Backup;

/** Read-only structural checks; never extracts or runs SQL. */
final class Preflight
{
    public static function inspect(string $directory, string $name, string $temp): array
    {
        $errors = []; $warnings = []; $names = []; $bytes = 0; $info = null;
        if (!class_exists(\ZipArchive::class)) return ['errors'=>['The PHP ZIP extension is missing.'],'warnings'=>[]];
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $name) || str_contains($name, '..')) return ['errors'=>['Invalid backup name.'],'warnings'=>[]];
        $parts = glob(rtrim($directory, '/\\') . '/' . $name . '-*.zip') ?: [];
        $indexes = [];
        foreach ($parts as $file) {
            if (!preg_match('/-(\d+)\.zip$/D', $file, $m)) continue;
            $indexes[] = (int) $m[1];
            $zip = new \ZipArchive();
            if ($zip->open($file, \ZipArchive::RDONLY | \ZipArchive::CHECKCONS) !== true) { $errors[] = basename($file) . ' is unreadable or damaged.'; continue; }
            for ($i=0; $i<$zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); $entry = (string) ($stat['name'] ?? '');
                if (str_contains($entry, '\\') || str_starts_with($entry, '/') || preg_match('~(^|/)\.\.(/|$)~', $entry)) $errors[] = 'Archive contains an unsafe path.';
                $names[$entry] = true; $bytes += (int) ($stat['size'] ?? 0);
            }
            if ((int) $m[1] === 0) {
                $stat = $zip->statName('info.txt');
                if ($stat && $stat['size'] <= 2 * 1024 * 1024) $info = @unserialize((string) $zip->getFromName('info.txt'), ['allowed_classes'=>['stdClass']]);
            }
            $zip->close();
        }
        sort($indexes);
        if (!$indexes || $indexes !== range(0, max($indexes ?: [0]))) $errors[] = 'One or more backup parts are missing.';
        if (!is_array($info) || !is_array($info['taskList'] ?? null)) $errors[] = 'Backup metadata is missing or invalid.';
        else {
            if ((int) ($info['format'] ?? 1) > 1) $errors[] = 'This backup uses a newer, unsupported format.';
            foreach ($info['taskList'] as $task) {
                if (!is_array($task) || !in_array($task['action'] ?? '', ['addMysqldump','addMediaDir','addSettings','addEnvironment','finishBackup'], true)) { $errors[] = 'Backup contains an unsupported restore task.'; continue; }
                if ($task['action'] === 'addMysqldump' && !isset($names['mysqldump_all.sql'])) $errors[] = 'The database dump is missing.';
                if ($task['action'] === 'addMediaDir' && !isset($names[(string) ($task['value'] ?? '') . '.zip'])) $errors[] = 'A media archive is missing.';
                if (in_array($task['action'], ['addSettings','addEnvironment'], true)) {
                    $prefix = $task['action'] === 'addSettings' ? 'settings/' : 'config/';
                    if (!array_filter(array_keys($names), static fn($entry) => str_starts_with($entry, $prefix))) $errors[] = 'A settings or environment archive group is missing.';
                }
            }
            if (!isset($info['format'])) $warnings[] = 'Older backup format: compatibility cannot be fully verified.';
        }
        $parent = $temp;
        while (!is_dir($parent) && dirname($parent) !== $parent) $parent = dirname($parent);
        if (!is_writable($parent)) $errors[] = 'Restore working directory is not writable.';
        $free = @disk_free_space($parent);
        if ($free !== false && $free < $bytes * 2) $warnings[] = 'Available disk space may be insufficient for extraction and replacement.';

        return ['errors'=>array_values(array_unique($errors)), 'warnings'=>array_values(array_unique($warnings))];
    }
}
