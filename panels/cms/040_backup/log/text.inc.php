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
$rows = [];
foreach (preg_split('/\R/', $text) ?: [] as $line) {
	$line = trim($line);
	if ($line === '') {
		continue;
	}
	if (preg_match('/^(.*?)\s+-\s+([\d.]+)s\s+([\d.]+)MB$/', $line, $match) === 1) {
		if (preg_match('/^\[([^\]]+)\] (.*)$/', $match[1], $dated)) {
            $rows[] = [$dated[2], $dated[1], $match[3] . ' MB'];
        } else {
            $rows[] = [trim($match[1]), $when($match[2]), $match[3] . ' MB'];
        }
	} else {
		$rows[] = [$line, '', ''];
	}
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
				<th>Memory</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($rows as $row) { ?>
				<tr<?= \Systopic\System\Logs\Filters::attributes($row[1], '', $row[0]) ?>>
					<td><?= $e($row[0]) ?></td>
					<td><?= $e($row[1]) ?></td>
					<td><?= $e($row[2]) ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
