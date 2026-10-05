<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Backup\Create;

use Systopic\System\Panels\Root\Cms\Backup\Panel as BackupPanel;

/**
 * Backup → Create sub-panel.
 *
 * Migrated from the legacy class backup_create_module (module.php).
 *
 * @property BackupPanel $parent
 */
class Panel extends BackupPanel
{
	public function buildGranted(): bool
	{
		return \Systopic\System\Auth\Session::hasOneRole('editor', 'textEditor');
	}
}
