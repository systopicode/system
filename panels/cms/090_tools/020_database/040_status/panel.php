<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Tools\Database\Status;

use Systopic\Db\Connection;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Schema\Status;
use Systopic\System\Panels\PanelNode;

class Panel extends PanelNode
{
	/** @var list<object>|null */
	private ?array $schemaTables = null;

	/**
	 * One read of the database, shared by the header and the list.
	 *
	 * @return array{
	 *     tables: list<object>,
	 *     shown: list<object>,
	 *     differ: list<object>,
	 *     agree: list<object>,
	 *     onlyDiffs: bool,
	 *     filter: string,
	 *     totalDiffs: int,
	 *     withModel: int
	 * }
	 */
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
	}

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
			return !$onlyDiffs || $table->differences > 0;
		}));
		$differ = [];
		$agree = [];
		foreach ($shown as $table) {
			if ($table->hasModel && $table->differences === 0) {
				$agree[] = $table;
			} else {
				$differ[] = $table;
			}
		}
		return [
			'tables' => $tables,
			'shown' => $shown,
			'differ' => $differ,
			'agree' => $agree,
			'onlyDiffs' => $onlyDiffs,
			'filter' => $filter,
			'totalDiffs' => (int) array_sum(array_map(static fn($table) => $table->differences, $tables)),
			'withModel' => count(array_filter($tables, static fn($table) => $table->hasModel)),
		];
	}
}
