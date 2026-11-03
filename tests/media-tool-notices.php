<?php
/** Disposable fixtures only: no downloads or executable invocation. */
require dirname(__DIR__) . '/lib/ffmpeg.bin.php';
$dir = sys_get_temp_dir() . '/fluxo-tool-notices-' . bin2hex(random_bytes(6));
mkdir($dir);
$check = static function (bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "ok: $name\n"; };
try {
    file_put_contents($dir . '/ffmpeg', 'fixture ffmpeg; never executed');
    file_put_contents($dir . '/ffprobe', 'fixture ffprobe; never executed');
    $downloads = ['ffmpeg' => ['url' => 'https://example.invalid/ffmpeg.zip', 'archiveSha256' => str_repeat('a', 64)], 'ffprobe' => ['url' => 'https://example.invalid/ffprobe.zip', 'archiveSha256' => str_repeat('b', 64)]];
    $check(cmsFfmpegWriteDistribution($dir, 'fixture-6.1', 'linux-64', $downloads), 'project-local records written');
    $path = $dir . '/licenses/ffmpeg/';
    $record = json_decode(file_get_contents($path . 'distribution.json'), true, 512, JSON_THROW_ON_ERROR);
    $check($record['version'] === 'fixture-6.1' && $record['platform'] === 'linux-64', 'exact reported version/platform retained');
    foreach (['ffmpeg', 'ffprobe'] as $name) {
        $check($record['binaries'][$name]['sha256'] === hash_file('sha256', $dir . '/' . $name), 'binary hash ' . $name);
        $check($record['binaries'][$name]['downloadUrl'] === $downloads[$name]['url'], 'download provenance ' . $name);
        $check($record['binaries'][$name]['archiveSha256'] === $downloads[$name]['archiveSha256'], 'archive hash ' . $name);
    }
    foreach ($record['generalNotices'] as $name => $hash) $check(hash_file('sha256', $path . $name) === $hash, 'notice hash ' . $name);
    $check(count($record['generalNotices']) === 4, 'general notices accompany binaries');
    $check($record['distributionStatus'] === 'requires-build-review' && $record['applicableLicense'] === null && $record['correspondingSources'] === [], 'unknown build is never represented as distribution-cleared');
    $check(cmsFfmpegWriteDistribution($dir, 'fixture-6.1', 'linux-64', $downloads), 'repeat update succeeds');
    $check(file_get_contents($dir . '/ffmpeg') === 'fixture ffmpeg; never executed', 'executable content untouched');
    $check(glob($path . '.distribution-*') === [], 'no partial temporary manifests remain');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($dir);
}
