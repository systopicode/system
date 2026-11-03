<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Backup\Create;

use Systopic\System\Panels\Root\Admin\Backup\Panel as BackupPanel;

/**
 * Backup → Create sub-panel.
 *
 * Migrated from the legacy class backup_create_module (module.php).
 *
 * @property BackupPanel $parent
 */
class Panel extends BackupPanel
{
	/** Who may: panel.config.json. Not the recovery user: without a database there is nothing to back up. */
	public function buildGranted(): bool
	{
		return \Systopic\System\Auth\Session::isSuperuser();
	}
}
