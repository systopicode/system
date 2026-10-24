<?php
/** @var \Systopic\System\Panels\PanelNode $this */

use Systopic\System\Auth\LoginLog;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$file = LoginLog::file();
$lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$lines = array_reverse($lines ?: []);
?>
<h1>Login log</h1>
<p class="tagsHint">Sign-ins, failed attempts and sign-outs. Newest first.</p>
<section class="widget is-list logList">
	<header>
		<h2>Sign-ins</h2>
		<input class="listSearch" type="search" placeholder="Search" data-on_input="filterLog" autocomplete="off">
		<a target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'downloadLog') ?>" class="button"><i class="fa-regular fa-file-arrow-down"></i><span>Download</span></a>
		<a data-on_click="userConfirm" data-action="?action=clearLog" data-confirm="Delete this log file?" class="button"><i class="fa-regular fa-broom"></i><span>Clear</span></a>
	</header>
	<?php if ($lines === []) { ?>
		<main><p>The log is empty.</p></main>
	<?php } else { ?>
		<table>
			<thead>
				<tr>
					<th>Date / time</th>
					<th>User</th>
					<th>Action</th>
					<th>IP</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($lines as $line) {
					$parts = array_map('trim', explode(' ; ', $line));
					?>
					<tr>
						<td><?= $e($parts[0] ?? '') ?></td>
						<td><?= $e($parts[1] ?? '') ?></td>
						<td><?= $e($parts[2] ?? '') ?></td>
						<td><?= $e($parts[3] ?? '') ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } ?>
</section>
