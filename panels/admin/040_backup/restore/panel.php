<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Backup\Restore;

use Systopic\System\Panels\Root\Admin\Backup\Panel as BackupPanel;

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
			// Settings rewrite the current configuration. They stay off until a
			// superuser turns them on.
			'restorePageSettings' => false,
			'restoreMediaSettings' => false,
		]);
	}
}
