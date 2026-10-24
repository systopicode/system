<?php

/**
 * Apply the model to one column, one table, or every table that still differs.
 * The audit runs again at that moment; anything no longer pending is skipped.
 *
 * @var \Systopic\System\Panels\PanelNode $this
 */

use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\AuditKind;
use Systopic\Db\Schema\Registry;

switch ($this->action) {
	case 'refreshStatus':
		$this->forgetSchema();
		$this->updateViews(['main', 'controls']);
		break;

	case 'applyTable':
		Registry::scanTables();
		$audit = new Audit(Connection::default());
		$current = $audit->run();
		$table = (string) (http::dataset('table') ?? '');
		$column = (string) (http::dataset('column') ?? '');
		$columnChange = static fn($action): bool => in_array($action->kind, [
			AuditKind::CreateTable,
			AuditKind::AddColumn,
			AuditKind::ModifyColumn,
			AuditKind::DropColumn,
		], true);
		$selected = array_values(array_filter($current, static function ($action) use ($table, $column, $columnChange): bool {
			if ($action->blocked || $action->statements === [] || !$columnChange($action)) {
				return false;
			}
			if ($table === '') {
				return true;
			}
			if ($column !== '') {
				return $action->subject === $table . '.' . $column;
			}
			return $action->subject === $table || str_starts_with($action->subject, $table . '.');
		}));
		$where = $column !== '' ? $table . '.' . $column : $table;
		if ($selected === []) {
			\message::error($where === ''
				? 'Database and model agree. Nothing to apply.'
				: 'Nothing pending for ' . $where . '.');
			$this->forgetSchema();
			$this->updateViews(['main', 'controls']);
			break;
		}
		$audit->apply($selected);
		$failures = $audit->failures();
		$ok = count($selected) - count($failures);
		if ($ok > 0) {
			\message::confirm('Applied ' . $ok . ' change' . ($ok === 1 ? '' : 's')
				. ($where !== '' ? ' to ' . $where : '') . '.');
		}
		foreach ($failures as $failure) {
			\message::error($failure['action']->subject . ': ' . $failure['message']);
		}
		$this->forgetSchema();
		$this->updateViews(['main', 'controls']);
		break;
}
