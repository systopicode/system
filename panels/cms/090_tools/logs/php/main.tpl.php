<?php
/** @var \Systopic\System\Panels\PanelNode $this */

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$path = rtrim((string) fs::$root, '/\\') . '/var/log/PHP_errors.log';
$raw = fs::isFile($path) ? (string) fs::file_get_contents($path, false, null, max(0, (int) fs::filesize($path) - 512 * 1024)) : '';

$entries = [];
$current = null;
foreach (preg_split('/\R/', $raw) ?: [] as $line) {
	if (preg_match('/^\[(.+?)\]\s*(.*)$/', $line, $match)) {
		if ($current !== null) {
			$entries[] = $current;
		}
		$body = $match[2];
		$level = 'Message';
		if (preg_match('/^PHP\s+([^:]+):\s*(.*)$/', $body, $levelMatch)) {
			$level = $levelMatch[1];
			$body = $levelMatch[2];
		}
		$current = ['time' => $match[1], 'level' => $level, 'message' => $body, 'trace' => ''];
	} elseif ($current !== null && trim($line) !== '') {
		$current['trace'] .= ($current['trace'] === '' ? '' : "\n") . $line;
	}
}
if ($current !== null) {
	$entries[] = $current;
}
$entries = array_reverse($entries);
?>
<h1>PHP log</h1>
<p class="tagsHint">PHP errors of this project, in <code>var/log/PHP_errors.log</code>. Newest first.</p>
<section class="widget is-list logList">
	<header>
		<h2>Entries</h2>
		<input class="listSearch" type="search" placeholder="Search" data-on_input="filterLog" autocomplete="off">
		<a target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'downloadLog') ?>" class="button"><i class="fa-regular fa-file-arrow-down"></i><span>Download</span></a>
		<a data-on_click="userConfirm" data-action="clearLog" data-confirm="Delete this log file?" class="button"><i class="fa-regular fa-broom"></i><span>Clear</span></a>
	</header>
	<?php if ($entries === []) { ?>
		<main><p>The log is empty.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>Date</th>
						<th>Level</th>
						<th>Message</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($entries as $entry) {
						$bad = preg_match('/fatal|error/i', $entry['level']) === 1;
						?>
						<tr>
							<td><?= $e($entry['time']) ?></td>
							<td><span class="logPill<?= $bad ? ' is-bad' : '' ?>"><?= $e($entry['level']) ?></span></td>
							<td>
								<?= $e($entry['message']) ?>
								<?php if (trim($entry['trace']) !== '') { ?>
									<pre class="logTrace"><?= $e($entry['trace']) ?></pre>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>
