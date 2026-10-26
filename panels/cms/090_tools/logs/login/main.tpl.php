<?php
/** @var \Systopic\System\Panels\PanelNode $this */

use Systopic\System\Auth\LoginLog;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$lines = LoginLog::entries();
// ?q= - a link from elsewhere (the user editor) opens the log filtered
$query = trim((string) ($_GET['q'] ?? ''));
$shown = static fn(array $parts): bool => $query === '' || mb_stripos(implode(' ', $parts) . ' ' . LoginLog::methodLabel($parts[4]), $query) !== false;
?>
<h1>Login log</h1>
<p class="tagsHint">Sign-ins, failed attempts and sign-outs. Newest first. For data protection, entries are deleted after <?= LoginLog::KEEP_DAYS ?> days.</p>
<section class="widget is-list logList">
	<header>
		<h2>Sign-ins</h2>
		<input class="listSearch" type="search" placeholder="Search" data-on_input="filterLog" autocomplete="off" value="<?= $e($query) ?>">
		<a target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'downloadLog') ?>" class="button"><i class="fa-regular fa-file-arrow-down"></i><span>Download</span></a>
		<a data-on_click="userConfirm" data-action="clearLog" data-confirm="Delete this log file?" class="button"><i class="fa-regular fa-broom"></i><span>Clear</span></a>
	</header>
	<?php if ($lines === []) { ?>
		<main><p>The log is empty.</p></main>
	<?php } else { ?>
		<div class="tableScroll">
			<table>
				<thead>
					<tr>
						<th>Date / time</th>
						<th>User</th>
						<th>Action</th>
						<th>Method</th>
						<th>IP</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($lines as $parts) { ?>
						<tr<?= $shown($parts) ? '' : ' hidden' ?>>
							<td><?= $e(($d = DateTimeImmutable::createFromFormat('d.m.Y-H:i:s', $parts[0])) ? \Systopic\System\Sys\LocalTime::format($d, 'd.m.Y H:i:s') : $parts[0]) ?></td>
							<td><?= $e($parts[1]) ?></td>
							<td><?= $e($parts[2]) ?></td>
							<td><?= $e(LoginLog::methodLabel($parts[4])) ?></td>
							<td><?= $e($parts[3]) ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>
