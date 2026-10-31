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
<p class="tagsHint">Entries older than <?= (int) \Systopic\System\Maintenance\LogRetention::LOGIN_DAYS ?> days are eligible for automatic cleanup. Runs depend on site visits or a configured scheduler. This is a fixed system rule, not a project setting. Superusers can clear entries earlier when they are no longer needed.</p>
<p class="tagsHint">Sign-ins, failed attempts and sign-outs. Newest first.</p>
<section class="widget is-list logList">
	<header>
		<h2>Sign-ins</h2>
		<input class="listSearch" type="search" aria-label="Search login log" placeholder="Search"  autocomplete="off" value="<?= $e($query) ?>">
		<a target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'downloadLog') ?>" class="button"><i class="cmsIcon cmsIcon-file-arrow-down"></i><span>Download</span></a>
		<?php if (\Systopic\System\Auth\Session::isSuperuser()) { ?>
		<button type="button" data-cms-action aria-label="Clear login log" data-on_click="userConfirm" data-action="clearLog" data-confirm="Delete this log file?" class="button"><i class="cmsIcon cmsIcon-broom"></i><span>Clear</span></button>
		<?php } ?>
	</header>
    <?= \Systopic\System\Logs\Filters::controls() ?>
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
						<tr<?= \Systopic\System\Logs\Filters::attributes((DateTimeImmutable::createFromFormat('d.m.Y-H:i:s', $parts[0]) ?: new DateTimeImmutable('@0'))->format('c'), $parts[1], $parts[2]) ?><?= $shown($parts) ? '' : ' hidden' ?>>
							<td><?= $e(($d = DateTimeImmutable::createFromFormat('d.m.Y-H:i:s', $parts[0])) ? \Systopic\System\Sys\LocalTime::format($d, 'd.m.Y H:i:s') : $parts[0]) ?></td>
							<td><?= \Systopic\System\Logs\Filters::userLink($parts[1]) ?></td>
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
