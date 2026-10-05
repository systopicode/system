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
    case 'applyLossless':
    case 'applyAll':
        Registry::scanTables();

        $audit   = new Audit(Connection::default());
        $current = $audit->run();

        // A single button in a row beats the ticks. Somebody pressing "run"
        // means that one action, not whatever else happened to be checked.
        $single = (string) (http::dataset('key') ?? '');

        $missing = 0;
        if ($single !== '') {
            $selected = array_values(array_filter(
                $current,
                static fn($action) => $action->key() === $single,
            ));
            $missing = 1 - count($selected);
        } elseif ($this->action === 'applyAll') {
            $selected = $current;
        } elseif ($this->action === 'applyLossless') {
            // Deliberately ignores the checkboxes: the point of this button is
            // "everything that cannot go wrong", not "everything ticked that
            // cannot go wrong". Blocked actions are out too — they lose
            // nothing, but they cannot run, and a button that reliably
            // produces errors stops being read.
            $selected = array_values(array_filter(
                $current,
                static fn($action) => !$action->destructive && !$action->blocked,
            ));
        } else {
            // The boxes are called `run:<key>` and send 1 or 0. One name per
            // action, because equally named fields overwrite each other in the
            // client.
            $wanted = [];
            foreach ((array) (http::form() ?: []) as $name => $value) {
                if (str_starts_with((string) $name, 'run:') && (string) $value !== '0') {
                    $wanted[] = substr((string) $name, 4);
                }
            }
            if ($wanted === []) {
                message::error('Nothing checked — nothing was run.');
                $this->updateView('message');
                break;
            }
            $selected = array_values(array_filter(
                $current,
                static fn($action) => in_array($action->key(), $wanted, true),
            ));
            $missing = count($wanted) - count($selected);
        }

        if ($selected === []) {
            message::error($current === []
                ? 'Database and model agree — nothing to run.'
                : 'None of the chosen actions is still pending — nothing was run.');
            $this->updateView('message');
            $this->updateView('main');
            break;
        }

        // Off unless explicitly ticked. It only ever helps foreign keys, and
        // then by not validating the rows that are already there.
        $skipFk = (string) (http::form('skipFk') ?? '0') !== '0';

        $done     = $audit->apply($selected, withoutForeignKeyChecks: $skipFk);
        $failures = $audit->failures();
        $ok       = count($selected) - count($failures);

        if ($ok > 0) {
            message::confirm(sprintf(
                'Ran %d of %d action%s, %d statement%s.%s%s',
                $ok, count($selected), count($selected) === 1 ? '' : 's',
                count($done), count($done) === 1 ? '' : 's',
                $missing > 0 ? " $missing no longer pending." : '',
                $skipFk ? ' Foreign key checks were off.' : '',
            ));
        }

        // Every failure by name. One error and silence about the other thirty
        // is what made this worth changing.
        foreach ($failures as $failure) {
            message::error(sprintf(
                '%s %s: %s',
                $failure['action']->kind->value,
                $failure['action']->subject,
                $failure['message'],
            ));
        }

        // Both: the message, and the list that just changed.
        $this->updateView('message');
        $this->updateView('main');
        break;
}
