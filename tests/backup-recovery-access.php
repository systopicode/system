<?php

declare(strict_types=1);

/**
 * The recovery user comes with one of two roles: `restore` for a database
 * without an account, `recovery` for one that cannot be reached. Both need the
 * backup panel, or the restore panel below it is out of reach. Only
 * `createDatabase` is for `recovery` alone. See .cursor/docs/setup.md.
 *
 *   php tests/backup-recovery-access.php
 */

function check(string $label, bool $ok): void
{
	if (!$ok) {
		throw new RuntimeException('FAIL: ' . $label);
	}
	echo "ok: $label\n";
}

$dir = dirname(__DIR__) . '/panels/admin/040_backup/';
$backup = json_decode((string) file_get_contents($dir . 'panel.config.json'), true);
$restore = json_decode((string) file_get_contents($dir . 'restore/panel.config.json'), true);

foreach (['recovery', 'restore'] as $role) {
	check("$role sees the backup panel", in_array($role, $backup['build'], true));
	foreach (['upload', 'download', 'remove', 'clearTempdirs'] as $action) {
		check("$role may $action", in_array($role, $backup['action'][$action], true));
	}
}
check('only recovery creates the database', $backup['action']['createDatabase'] === ['recovery']);
check('restore reaches the restore panel', in_array('restore', $restore['build'], true));
echo "all fine\n";
