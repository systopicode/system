<?php

/**
 * The audit as a sequence: look, choose, run.
 *
 * The list is the confirmation. There is no way to trigger a change without
 * having read it first, which is why none of the three buttons skips the list.
 *
 * Three ways to run, because ticking thirty boxes to get everything is not a
 * safety feature, it is an obstacle:
 *
 *   run checked          what is ticked — lossless ones are ticked on arrival
 *   run lossless only    everything that cannot lose data, ticked or not
 *   run all              everything, including what discards data
 *
 * Only the last one asks. It is the only one that can throw something away,
 * and it uses `userConfirm` rather than `confirmRequired()`: the server-side
 * dialog needs a `confirmbox` view hosted somewhere up the panel chain, and
 * there is none in this installation — the question would be armed and the
 * dialog would never render, so nothing would ever run.
 *
 * Which action is destructive comes from `AuditAction::$destructive`, never
 * from a list here. Otherwise the display would hold its own opinion about
 * what is dangerous.
 *
 * Between showing and running, the audit runs **again**. What gets applied is
 * never the plan on screen but the current one; the selection travels as names
 * and anything that has since gone is simply not found.
 *
 * **Everything goes by PUT, nothing appears in the address bar.** An
 * `<a href=…>` without `data-on_click` is forced to GET by the client
 * (event.pathItem.js), which puts the action name in the path — a URL that
 * shows nothing when opened, because it is not a page. So the buttons carry
 * `data-on_click` and no href, and the form has no `method`, which the client
 * reads as PUT and puts the fields into `put->form`.
 *
 * The checkbox names differ per action (`run:<key>`) rather than being
 * `run[]`: `getInputData()` builds `{name: value}`, so equal names overwrite
 * each other and exactly one of thirty-five ticks would arrive.
 */

use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\AuditKind;
use Systopic\Db\Schema\Registry;

$this->withView('message');
?>
<style>
    .auditList { width: 100%; border-collapse: collapse; font-size: .9em; }
    .auditList th { text-align: left; padding: 4px 10px 4px 0; opacity: .55; font-weight: normal; }
    .auditList td { padding: 4px 10px 4px 0; vertical-align: top; }
    .auditList tr.destructive td.auditKind { color: #b3261e; font-weight: bold; }
    .auditList tr.blocked td { opacity: .65; }
    .auditList tr.blocked td.auditKind { color: #8a6d00; font-weight: bold; }
    .auditList tr:hover { background: rgba(0,0,0,.04); }
    .auditKind { white-space: nowrap; font-family: monospace; }
    .auditSubject { font-family: monospace; white-space: nowrap; }
    .auditSql { font-family: monospace; font-size: .85em; opacity: .6; display: block; white-space: pre-wrap; }
    .auditGroup td { padding-top: 18px; font-weight: bold; }
    .auditFooter { margin-top: 18px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,.15); }
    .auditFooter .button { margin-right: 8px; }
    .auditCount { opacity: .6; }
</style>

<?php
// Runs on arrival — it only reads, so there is nothing to protect by asking
// first. Running it again is the refresh button in the controls.
Registry::scanTables();
// Already sorted by danger — Audit::run() does it, so that what is shown here
// and what gets executed are the same sequence and not two that happen to
// agree.
$actions = (new Audit(Connection::default()))->run();

$destructive = count(array_filter($actions, static fn($a) => $a->destructive));
$blocked     = count(array_filter($actions, static fn($a) => $a->blocked));
// What "Run lossless only" would actually attempt: nothing that loses data and
// nothing that is already known to fail.
$lossless    = count(array_filter($actions, static fn($a) => !$a->destructive && !$a->blocked));
?>

<h1>Audit <span class="auditCount">&mdash;
    <?= count($actions) ?> actions, <?= $destructive ?> of them discard data<?=
    $blocked > 0 ? ', ' . $blocked . ' would fail on the data as it is' : '' ?></span></h1>

<?php if ($actions === []): ?>
    <p>Database and model agree. Nothing to do.</p>
    <?php return; ?>
<?php endif; ?>

<?php // no method= — the client reads that as PUT and puts the fields into put->form ?>
<form action="applyAudit">
    <table class="auditList">
        <tr>
            <th style="width:2em"></th>
            <th>Kind</th><th>Subject</th><th>Finding</th><th style="width:6em"></th>
        </tr>
        <?php $lastKind = null; ?>
        <?php foreach ($actions as $action): ?>
            <?php if ($action->kind !== $lastKind): $lastKind = $action->kind; ?>
                <tr class="auditGroup"><td colspan="5"><?= htmlspecialchars($action->kind->value) ?></td></tr>
            <?php endif; ?>
            <tr class="<?= $action->destructive ? 'destructive' : '' ?><?= $action->blocked ? ' blocked' : '' ?>">
                <td>
                    <input type="checkbox"
                           name="run:<?= htmlspecialchars($action->key(), ENT_QUOTES) ?>"
                           value="1"
                           <?= $action->destructive || $action->blocked ? '' : 'checked' ?>>
                </td>
                <td class="auditKind"><?= htmlspecialchars($action->kind->value) ?></td>
                <td class="auditSubject"><?= htmlspecialchars($action->subject) ?></td>
                <td>
                    <?= htmlspecialchars($action->describe) ?>
                    <?php foreach ($action->statements as $sql): ?>
                        <span class="auditSql"><?= htmlspecialchars($sql) ?></span>
                    <?php endforeach; ?>
                    <?php if ($action->statements === []): ?>
                        <span class="auditSql">(runs as code, not as SQL)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($action->statements !== []): ?>
                        <?php // An <a>, not a <button>: every submit button in the form
                              // would send its value and the last would win, so which one
                              // was clicked would no longer be recoverable. ?>
                        <a class="submit" data-on_click="applyAudit"
                           data-key="<?= htmlspecialchars($action->key(), ENT_QUOTES) ?>"
                        >run</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="auditFooter">
        <button type="submit" class="button">Run checked</button>
        <a class="button submit" data-on_click="applyLossless"
        >Run lossless only (<?= $lossless ?>)</a>
        <a class="button submit" data-on_click="userConfirm"
           data-action="applyAll"
           data-confirm="Run all <?= count($actions) ?> actions, including <?= $destructive ?> that discard data?"
        >Run all (<?= count($actions) ?>)</a>
        <p class="auditCount">
            Red actions discard data. Amber ones would fail on the data as it stands —
            the finding says how many rows point nowhere. Neither is pre-selected.
            &ldquo;Run lossless only&rdquo; ignores the checkboxes and runs everything
            that can neither lose anything nor fail. A failing action no longer stops
            the rest — each one is reported by name.
        </p>
        <p>
            <label title="Creates foreign keys without validating the rows that are already there.">
                <input type="checkbox" name="skipFk" value="1">
                add foreign keys without checking existing rows
            </label>
            <span class="auditCount">
                &mdash; the only thing this helps with. Existing violations stay in the
                data and surface later; it does nothing for a duplicate in a unique key.
            </span>
        </p>
    </div>
</form>
