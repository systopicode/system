<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Backup\Restore;

use Systopic\System\Panels\Root\Cms\Backup\Panel as BackupPanel;

/**
 * Backup → Restore sub-panel.
 *
 * Migrated from the legacy class backup_restore_module (module.php).
 *
 * @property BackupPanel $parent
 */
class Panel extends BackupPanel
{
	/** Who may: panel.config.json. The recovery user before a restore (an empty database) may too. */
	public function buildGranted(): bool
	{
		return \Systopic\System\Auth\Session::isRecovery() ? \Systopic\System\Auth\Session::hasRole('restore') : \Systopic\System\Auth\Session::isSuperuser();
	}

	public function onLoad(): void
	{
		$name = self::backupName(\http::get('name'));
		if ($name === null || ($this->action !== null && $this->action !== '')) {
			return;
		}
		if (($this->parent->state->restoreName ?? '') === $name) {
			return;
		}
		$contents = $this->parent->contentsOfBackup($name);
		if ($contents === null) {
			return;
		}
		$this->parent->updateState((object) [
			'restoreName' => $name,
			'restoreDatabase' => !empty($contents['database']),
			'restoreMedia' => !empty($contents['media']),
			// Settings and the environment rewrite sign-in, mail and the theme.
			// They stay off until a superuser turns them on.
			'restorePageSettings' => false,
			'restoreMediaSettings' => false,
			'restoreEnvironment' => false,
		]);
	}
}
