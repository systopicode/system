<?php

/**
 * Two actions only: remove an orphaned column, remove an orphaned table.
 * Every other change is the audit's, where it is listed with its SQL.
 *
 * The audit runs again here; what is run is the current drop action of that
 * name, never anything else - a key that is no longer an orphan drop is not
 * found, and nothing runs.
 *
 * @var \Systopic\System\Panels\Root\Cms\Tools\Database\Status\Panel $this
 */

use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\Registry;

switch ($this->action) {
	case 'refreshStatus':
		$this->forgetSchema();
		$this->updateViews(['main', 'controls']);
		break;

	case 'removeOrphan':
		Registry::scanTables();
		$audit = new Audit(Connection::default());
		$key = (string) (http::dataset('key') ?? '');
		$selected = array_values(array_filter(
			$audit->run(),
			fn($action) => $action->key() === $key && $this::isOrphanDrop($action),
		));
		if ($selected === []) {
			\message::error('Nothing to remove: ' . ($key === '' ? 'no action named.' : $key . ' is no longer an orphan.'));
		} else {
			$audit->apply($selected);
			$failure = $audit->failures()[0] ?? null;
			if ($failure === null) {
				\message::confirm('Removed ' . $selected[0]->subject . '.');
			} else {
				\message::error($failure['action']->subject . ': ' . $failure['message']);
			}
		}
		$this->forgetSchema();
		$this->updateViews(['message', 'main', 'controls']);
		break;
}
