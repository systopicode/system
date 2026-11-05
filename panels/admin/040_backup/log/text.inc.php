<?php

$path = rtrim((string) \fs::$siteRoot, '/\\') . '/var/backups/backup.log';
$text = \fs::isFile($path) ? (string) \fs::file_get_contents($path) : '';
$when = static function (string $raw): string {
	$seconds = (float) $raw;
	// The log stores a clock time as seconds since 1970. A short run is a duration.
	if ($seconds >= 1000000000) {
		return \Systopic\System\Sys\LocalTime::format((int) $seconds, 'Y-m-d H:i:s');
	}
	return rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.') . ' s';
};
$displayTime = static function (string $raw): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]/', $raw)) return $raw;
    try { return (new \DateTimeImmutable($raw))->format(\Systopic\System\Sys\LocalTime::DATETIME_SECONDS); }
    catch (\Throwable) { return $raw; }
};
$rows = [];
foreach (preg_split('/\R/', $text) ?: [] as $line) {
	$line = trim($line);
	if ($line === '') {
		continue;
	}
    $record = \Systopic\System\Backup\LogEntry::parse($line);
    $time = $record['date'] !== '' ? $record['date'] : ($record['elapsed'] !== '' ? $when($record['elapsed']) : '');
    $rows[] = [$record['event'], $time, $record['memory'], $record['user']];
}
$rows = array_reverse($rows);
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
if ($rows === []) {
	echo '<p class="tagsHint">The log is empty.</p>';
	return;
}
?>
<div class="tableScroll">
	<table>
		<thead>
			<tr>
				<th>Event</th>
				<th>Time</th>
				<th>User</th>
				<th>Memory</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($rows as $row) { ?>
				<tr<?= \Systopic\System\Logs\Filters::attributes($row[1], $row[3], $row[0]) ?>>
					<td><?= $e($row[0]) ?></td>
					<td><?= $e($displayTime($row[1])) ?></td>
					<td><?= $row[3] !== '' ? \Systopic\System\Logs\Filters::userLink($row[3]) : 'Not recorded' ?></td>
					<td><?= $e($row[2]) ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
