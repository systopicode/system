<?php

/**
 * Run what was confirmed in the list — and nothing else.
 *
 * The audit runs **again** here. What gets applied is not the plan the browser
 * saw but the current one; the selection only travels as a list of names.
 * Anything meanwhile done or gone is not found again and is passed over
 * silently — the right answer to the fact that time passes between showing a
 * list and clicking on it.
 *
 * Three ways in, one body: what differs is only which actions end up selected.
 *
 * @var \Systopic\System\Panels\PanelNode $this
 */

use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\AuditKind;
use Systopic\Db\Schema\Registry;

switch ($this->action) {
    case 'runAudit':
        // The list is built in main.tpl.php from $this->action — all there is
        // to do here is say that this view has to be drawn again. Without it
        // the answer to the button comes back without anything visibly
        // changing.
        $this->updateView('main');
        break;

    case 'applyAudit':
        Registry::scanTables();

        $audit   = new Audit(Connection::default());
        $current = $audit->run();
        $single  = (string) (http::dataset('key') ?? '');
        $table   = (string) (http::dataset('table') ?? '');

        $selected = array_values(array_filter(
            $current,
            static function ($action) use ($single, $table): bool {
                if ($action->blocked || $action->statements === []) {
                    return false;
                }
                if ($single !== '') {
                    return $action->key() === $single;
                }
                if ($table === 'Migrations') {
                    return $action->kind === AuditKind::Migration;
                }
                return $table !== ''
                    && ($action->subject === $table || str_starts_with($action->subject, $table . '.'));
            },
        ));

        $where = $single !== '' ? $single : $table;
        if ($selected === []) {
            message::error($where === ''
                ? 'Database and model agree. Nothing to apply.'
                : 'Nothing pending for ' . $where . '.');
            $this->updateView('message');
            $this->updateView('main');
            break;
        }

        $audit->apply($selected);
        $failures = $audit->failures();
        $ok       = count($selected) - count($failures);

        if ($ok > 0) {
            message::confirm('Applied ' . $ok . ' action' . ($ok === 1 ? '' : 's')
                . ($where !== '' ? ' for ' . $where : '') . '.');
        }
        foreach ($failures as $failure) {
            message::error($failure['action']->subject . ': ' . $failure['message']);
        }

        // Both: the message, and the list that just changed.
        $this->updateView('message');
        $this->updateView('main');
        break;
}
