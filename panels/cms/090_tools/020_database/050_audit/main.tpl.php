<?php

/**
 * The audit as a sequence: look, choose, run.
 *
 * The list is the confirmation: every action with its finding and its SQL.
 * Four ways to run, because ticking thirty boxes to get everything is not a
 * safety feature, it is an obstacle:
 *
 *   run checked                   what is ticked - lossless ones are ticked on arrival
 *   run checked without FK checks the same with FOREIGN_KEY_CHECKS = 0
 *   run lossless only             everything that can neither lose data nor fail
 *   run all                       everything, including what discards data (asks)
 *
 * Which action is destructive comes from `AuditAction::$destructive`, never
 * from a list here. Between showing and running the audit runs again: the
 * selection travels as names, anything gone meanwhile is not found.
 *
 * Everything goes by PUT: the buttons carry `data-on_click` and no href, the
 * form has no `method` (the client reads that as PUT, fields in `put->form`).
 * The checkboxes are named `run:<key>` each - equal names (`run[]`) would
 * overwrite each other in `getInputData()`.
 *
 * @var \Systopic\System\Panels\Root\Cms\Tools\Database\Audit\Panel $this
 */

use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\Registry;

$this->withView('message');

// Runs on arrival - it only reads. Already sorted by danger (Audit::run()),
// so what is shown and what is executed are the same sequence.
Registry::scanTables();
$actions = (new Audit(Connection::default()))->run();

$destructive = count(array_filter($actions, static fn($a) => $a->destructive));
$blocked     = count(array_filter($actions, static fn($a) => $a->blocked));
// what "Run lossless only" would attempt: nothing that loses data, nothing known to fail
$lossless    = count(array_filter($actions, static fn($a) => !$a->destructive && !$a->blocked));

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$groups = [];
foreach ($actions as $action) {
	$groups[$action->kind->value][] = $action;
}
?>
<h1>Audit</h1>
<p class="tagsHint">
	<?= count($actions) ?> actions, <?= $destructive ?> of them discard data<?= $blocked > 0 ? ', ' . $blocked . ' would fail on the data as it is' : '' ?>.
	Every action that would make the database match the model: tables, columns, indexes, foreign keys, orphaned rows and migrations.
</p>
<?php // refresh: the header bar (controls.tpl.php) ?>

<?php if ($actions === []): ?>
	<p class="tagsHint">Database and model agree. Nothing to do.</p>
	<?php return; ?>
<?php endif; ?>

<?php // no method= - the client reads that as PUT and puts the fields into put->form ?>
<form action="applyAudit" class="auditForm">
	<?php foreach ($groups as $kind => $group):
		$discard = count(array_filter($group, static fn($a) => $a->destructive));
		$fail = count(array_filter($group, static fn($a) => $a->blocked));
		?>
		<section class="widget is-list">
			<header>
				<h2><?= $e($kind) ?></h2>
				<span class="logPill"><?= count($group) ?></span>
				<?php if ($discard > 0): ?><span class="logPill is-bad"><?= $discard ?> discard data</span><?php endif; ?>
				<?php if ($fail > 0): ?><span class="logPill"><?= $fail ?> would fail</span><?php endif; ?>
			</header>
			<div class="tableScroll">
				<table class="auditList">
					<thead>
						<tr><th class="auditCheck"></th><th>Subject</th><th>Finding</th><th></th></tr>
					</thead>
					<tbody>
						<?php foreach ($group as $action):
							$row = trim(($action->destructive ? 'destructive' : '') . ' ' . ($action->blocked ? 'blocked' : ''));
							?>
							<tr class="<?= $row ?>">
								<td class="auditCheck">
									<input type="checkbox" name="run:<?= $e($action->key()) ?>" value="1"<?= $action->destructive || $action->blocked ? '' : ' checked' ?>>
								</td>
								<td><span class="auditSubject"><?= $e($action->subject) ?></span></td>
								<td>
									<?= $e($action->describe) ?>
									<?php foreach ($action->statements as $sql): ?>
										<code class="auditStatement"><?= $e($sql) ?></code>
									<?php endforeach; ?>
									<?php if ($action->statements === []): ?>
										<span class="auditNote">Runs as code, not as SQL.</span>
									<?php endif; ?>
								</td>
								<td class="schemaApply">
									<?php if ($action->statements !== []):
										// An <a>, not a <button>: every submit button in the form would
										// send its value and the last would win. Only what discards data asks.
										if ($action->destructive): ?>
											<a class="button" data-on_click="userConfirm" data-action="applyAudit" data-key="<?= $e($action->key()) ?>"
											   data-confirm="<?= $e('Run ' . $action->kind->value . ' on ' . $action->subject . '? ' . $action->describe . '. This discards data.') ?>">Run</a>
										<?php else: ?>
											<a class="button submit" data-on_click="applyAudit" data-key="<?= $e($action->key()) ?>">Run</a>
										<?php endif;
									endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endforeach; ?>

	<section class="widget auditRun">
		<header><h2>Run</h2></header>
		<div class="auditButtons">
			<button type="submit" class="button submit">Run checked</button>
			<?php // the same body as "Run checked", with FOREIGN_KEY_CHECKS = 0 for the run ?>
			<a class="button" data-on_click="applyAudit" data-nofk="1"
			   title="Runs the checked actions with FOREIGN_KEY_CHECKS = 0, switched back on afterwards.">Run checked without FK checks</a>
			<a class="button" data-on_click="applyLossless">Run lossless only (<?= $lossless ?>)</a>
			<a class="button" data-on_click="userConfirm" data-action="applyAll"
			   data-confirm="<?= $e('Run all ' . count($actions) . ' actions, including ' . $destructive . ' that discard data?') ?>">Run all (<?= count($actions) ?>)</a>
		</div>
		<p class="tagsHint">
			Red rows discard data, amber ones would fail on the data as it stands - the finding says how many rows
			point nowhere. Neither is pre-selected. “Run lossless only” ignores the checkboxes and runs everything that
			can neither lose anything nor fail. A failing action does not stop the rest - each one is reported by name.
		</p>
		<p class="tagsHint">
			“Run checked without FK checks” is for two cases: a column a foreign key points at has to change
			(MySQL error 1833, e.g. a key getting AUTO_INCREMENT), and a foreign key is to be added without validating
			the rows already there (those violations stay in the data and surface later). It does nothing for a
			duplicate in a unique key.
		</p>
	</section>
</form>
