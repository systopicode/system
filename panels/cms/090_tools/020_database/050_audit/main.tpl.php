<?php

/**
 * Same operation as database status: one Apply per action, Apply all per
 * table. The audit runs again at the moment of applying, so a name that no
 * longer matches is simply skipped.
 */

use Systopic\Db\Connection;
use Systopic\Db\Schema\Audit;
use Systopic\Db\Schema\AuditKind;
use Systopic\Db\Schema\Registry;

$this->withView('message');
?>
<?php
// Runs on arrival — it only reads, so there is nothing to protect by asking
// first. Running it again is the Refresh button on the card.
Registry::scanTables();
// Already sorted by danger — Audit::run() does it, so that what is shown here
// and what gets executed are the same sequence and not two that happen to
// agree.
$actions = (new Audit(Connection::default()))->run();

$destructive = count(array_filter($actions, static fn($a) => $a->destructive));
$blocked     = count(array_filter($actions, static fn($a) => $a->blocked));
?>

<?php
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$groups = [];
foreach ($actions as $action) {
	$name = $action->kind === AuditKind::Migration
		? 'Migrations'
		: explode('.', $action->subject, 2)[0];
	$groups[$name][] = $action;
}
?>
<h1>Audit</h1>
<p class="tagsHint">Status only compares columns. The audit is every action that would make the database match the model, including indexes, foreign keys, orphaned rows and migrations. Apply runs one action. Apply all runs that table. <?= count($actions) ?> actions. <?= $destructive ?> discard data<?= $blocked > 0 ? ', ' . $blocked . ' would fail' : '' ?>.</p>

<?php if ($actions === []): ?>
	<p class="tagsHint">Database and model agree. Nothing to do.</p>
	<?php return; ?>
<?php endif; ?>

<?php $filter = (string) \http::get('table', ''); ?>
<div class="schemaBar">
	<form class="schemaFilters" method="get" action="./">
		<select name="table" class="onchangesubmit">
			<option value="">All tables</option>
			<?php foreach ($groups as $name => $group): ?>
				<option value="<?= $e($name) ?>"<?= $filter === $name ? ' selected' : '' ?>><?= $e($name) ?> (<?= count($group) ?>)</option>
			<?php endforeach; ?>
		</select>
	</form>
	<a class="button" data-on_click="runAudit">Refresh</a>
</div>

<?php
$shown = $filter === '' ? $groups : array_intersect_key($groups, [$filter => true]);
if ($shown === []): ?>
	<p class="tagsHint">No table matches the selection.</p>
<?php endif; ?>

	<?php foreach ($shown as $name => $group):
		$discard = count(array_filter($group, static fn($action) => $action->destructive));
		$fail = count(array_filter($group, static fn($action) => $action->blocked));
		?>
		<section class="widget is-list">
			<header>
				<h2><?= $e($name) ?></h2>
				<span class="logPill"><?= count($group) ?></span>
				<?php if ($discard > 0): ?><span class="logPill is-bad"><?= $discard ?> discard data</span><?php endif; ?>
				<?php if ($fail > 0): ?><span class="logPill"><?= $fail ?> would fail</span><?php endif; ?>
			</header>
			<table>
				<thead>
					<tr><th>Action</th><th>Finding</th><th></th></tr>
				</thead>
				<tbody>
					<?php foreach ($group as $action):
						$tail = explode('.', $action->subject, 2)[1] ?? '';
						$row = trim(($action->destructive ? 'destructive' : '') . ' ' . ($action->blocked ? 'blocked' : ''));
						$ask = 'Apply ' . $action->kind->value . ' on ' . $action->subject . '?'
							. ($action->destructive ? ' This discards data.' : '');
						?>
						<tr class="<?= $row ?>">
							<td>
								<span class="auditKind"><?= $e($action->kind->value) ?></span>
								<?php if ($tail !== ''): ?><span class="auditSubject"><?= $e($tail) ?></span><?php endif; ?>
							</td>
							<td>
								<?= $e($action->describe) ?>
								<?php if ($action->statements !== []): ?>
									<details class="auditSql">
										<summary>SQL</summary>
										<?php foreach ($action->statements as $sql): ?>
											<pre><?= $e($sql) ?></pre>
										<?php endforeach; ?>
									</details>
								<?php else: ?>
									<span class="auditNote">Runs as code, not as SQL.</span>
								<?php endif; ?>
							</td>
							<td class="schemaApply">
								<?php if ($action->statements !== []): ?>
									<a class="button submit" data-on_click="userConfirm" data-action="applyAudit" data-key="<?= $e($action->key()) ?>" data-confirm="<?= $e($ask) ?>">Apply</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$runnable = count(array_filter($group, static fn($action) => $action->statements !== [] && !$action->blocked));
			if ($runnable > 0):
				$askAll = 'Apply all actions for ' . $name . '?' . ($discard > 0 ? ' ' . $discard . ' discard data.' : '');
				?>
				<footer>
					<a class="button submit" data-on_click="userConfirm" data-action="applyAudit" data-table="<?= $e($name) ?>" data-confirm="<?= $e($askAll) ?>">Apply all</a>
				</footer>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
