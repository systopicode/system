<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Tools\Database\Status;

use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\AuditAction;
use Systopic\Db\Schema\AuditKind;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Schema\Status;
use Systopic\System\Panels\PanelNode;

/**
 * Database against model, side by side - every table, every column.
 *
 * It reads, with two exceptions: an orphaned column (in the database, not in
 * the model) and an orphaned table (no class under a prefix the model uses)
 * can be removed here. Everything else that would change is decided in the
 * audit next door, where each action is listed with its SQL.
 */
class Panel extends PanelNode
{
	/** @var list<object>|null */
	private ?array $schemaTables = null;

	/** @var array<string, AuditAction>|null drop column / drop table by subject */
	private ?array $orphans = null;

	/**
	 * A filter is a get to this same panel. The layer identity does not
	 * change, so the client would keep the old list unless these views
	 * are marked for update.
	 */
	public function onLoadSelected(): void
	{
		if (\http::method() === 'GET') {
			$this->updateViews(['main', 'controls']);
		}
	}

	public function forgetSchema(): void
	{
		$this->schemaTables = null;
		$this->orphans = null;
	}

	/**
	 * One read of the database, shared by the header and the list.
	 *
	 * @return array{tables: list<object>, shown: list<object>, onlyDiffs: bool, filter: string, totalDiffs: int, withModel: int}
	 */
	public function schemaState(): array
	{
		if ($this->schemaTables === null) {
			Registry::scanTables();
			$this->schemaTables = (new Status(Connection::default()))->tables();
		}
		$tables = $this->schemaTables;
		$onlyDiffs = \http::get('diffs', '') === '1';
		$filter = (string) \http::get('table', '');
		$shown = array_values(array_filter($tables, static function ($table) use ($onlyDiffs, $filter) {
			if ($filter !== '' && $table->name !== $filter) {
				return false;
			}
			return !$onlyDiffs || $table->differences > 0 || !$table->hasModel;
		}));
		return [
			'tables' => $tables,
			'shown' => $shown,
			'onlyDiffs' => $onlyDiffs,
			'filter' => $filter,
			'totalDiffs' => (int) array_sum(array_map(static fn($table) => $table->differences, $tables)),
			'withModel' => count(array_filter($tables, static fn($table) => $table->hasModel)),
		];
	}

	/**
	 * What the audit offers to remove, by subject (`table.column`, `table`).
	 * A button only where the audit has the action: a table without a primary
	 * key, for one, is out of its scope and gets none.
	 *
	 * @return array<string, AuditAction>
	 */
	public function orphans(): array
	{
		if ($this->orphans === null) {
			Registry::scanTables();
			$this->orphans = [];
			foreach ((new Audit(Connection::default()))->run() as $action) {
				if (self::isOrphanDrop($action)) {
					$this->orphans[$action->subject] = $action;
				}
			}
		}
		return $this->orphans;
	}

	/** The only two actions this panel may run. */
	public static function isOrphanDrop(AuditAction $action): bool
	{
		return ($action->kind === AuditKind::DropColumn || $action->kind === AuditKind::DropTable)
			&& $action->statements !== [] && !$action->blocked;
	}
}
