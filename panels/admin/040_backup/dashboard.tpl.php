<?php
/** Dashboard: the newest backup. @var \Systopic\System\Panels\Root\Admin\Backup\Panel $this */

use Systopic\System\Panels\Root\Admin\Panel as Cms;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$backups = $this->getBackupfilestable(\fs::$siteRoot . 'var/backups/');
$last = $backups[0] ?? null;
$days = $last === null ? null : (int) floor((time() - (int) strtotime((string) $last['date'])) / 86400);
?>
<header><h2><i class="cmsIcon cmsIcon-box-archive"></i>Backup</h2></header>
<main class="dashBackup">
	<?php if ($last === null) { ?>
		<p><i class="cmsIcon cmsIcon-triangle-exclamation is-warning"></i> No backup yet.</p>
	<?php } else { ?>
		<p class="<?= $days > 7 ? 'dashWarn' : '' ?>">
			<strong>Last backup <?= $e(Cms::ago((string) $last['date'])) ?></strong>
			<small><?= $e(implode(' · ', array_filter([(string) $last['totalsize'], (string) ($last['contentsLabel'] ?? ''), (string) $last['user']]))) ?></small>
		</p>
		<p class="hint"><?= count($backups) ?> backup<?= count($backups) === 1 ? '' : 's' ?> kept.</p>
	<?php } ?>
</main>
<footer>
	<a class="ajax button" href="<?= $e(\http::$root . 'admin/backup/') ?>"><i class="cmsIcon cmsIcon-box-archive"></i><span>Open Backup</span></a>
	<?php if ($last === null || $days > 7) { ?>
		<a class="ajax button submit" href="<?= $e(\http::$root . 'admin/backup/create/') ?>"><i class="cmsIcon cmsIcon-plus"></i><span>Create backup</span></a>
	<?php } ?>
</footer>
