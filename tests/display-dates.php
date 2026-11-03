<?php
require __DIR__ . '/bootstrap.php';
use Systopic\System\Sys\LocalTime;
date_default_timezone_set('UTC');
$_COOKIE[LocalTime::COOKIE] = 'Europe/Berlin';
check('short European date has leading zeroes and four-digit year', LocalTime::format('2026-01-02 12:34:56', LocalTime::DATE) === '02.01.2026');
check('default display includes local time', LocalTime::format('2026-01-02 12:34:56') === '02.01.2026 13:34');
check('seconds retained where needed', LocalTime::format('2026-01-02 12:34:56', LocalTime::DATETIME_SECONDS) === '02.01.2026 13:34:56');
check('missing dates stay empty', LocalTime::format(null) === '' && LocalTime::format('') === '');
check('explicit machine format is unchanged', LocalTime::format('2026-01-02 12:34:56', 'Y-m-d H:i:s') === '2026-01-02 13:34:56');
$from = new DateTimeImmutable('2026-11-03 02:00:00 UTC');
$now = new DateTimeImmutable('2026-11-01 12:00:00 UTC');
$state = \Systopic\Cms\Pages\PublishingState::of(true, false, true, $from, null, $now);
check('publishing uses short date but preserves server time', str_contains($state['reason'], '03.11.2026 02:00'));

class fs {
    public static string $siteRoot;
    public static function isFile(string $path): bool { return is_file($path); }
    public static function file_get_contents(string $path): string { return file_get_contents($path); }
}
$fixture = sys_get_temp_dir() . '/fluxo-date-log-' . bin2hex(random_bytes(5));
mkdir($fixture . '/var/backups', 0777, true);
fs::$siteRoot = $fixture;
try {
    file_put_contents($fixture . '/var/backups/backup.log', "[2026-11-01T12:34:56+00:00] Finished - 1s 4MB\nUndated legacy entry - 1.2s 4MB\n");
    ob_start();
    include dirname(__DIR__) . '/panels/admin/040_backup/log/text.inc.php';
    $html = ob_get_clean();
    check('backup log displays European dates', str_contains($html, '01.11.2026 12:34:56'));
    check('backup log keeps machine timestamp for filtering', str_contains($html, 'data-log-time="' . strtotime('2026-11-01T12:34:56+00:00') . '"'));
    check('durations are not mistaken for dates', str_contains($html, '1.2 s'));
} finally {
    unlink($fixture . '/var/backups/backup.log');
    rmdir($fixture . '/var/backups'); rmdir($fixture . '/var'); rmdir($fixture);
}
