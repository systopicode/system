<?php

/**
 * Database against model, side by side - every table, every column.
 *
 * Database, then model. The colours follow from that: the database value of
 * a differing column is what would change, the model value is the reference
 * it would change towards. A row without colour is a row nobody has to think
 * about. Compared through `Column::comparable()`, the rule the audit uses, so
 * this view cannot show a column as matching that the audit then changes.
 *
 * The only actions: remove an orphaned column, remove an orphaned table
 * (actions.php). Everything else is applied in the audit.
 *
 * @var \Systopic\System\Panels\Root\Cms\Tools\Database\Status\Panel $this
 */

use Systopic\Db\Schema\StatusState;

$this->withView('message');
$state = $this->schemaState();
$orphans = $this->orphans();

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
/** One side of a column: the value, coloured where it differs, a dash where there is none. */
$cell = static function (?string $text, bool $differs, string $class) use ($e): string {
	if ($text === null) {
		return '<td class="schemaNothing">—</td>';
	}
	return '<td class="schemaValue' . ($differs ? ' ' . $class : '') . '">' . $e($text) . '</td>';
};
/** The remove button for an orphan the audit offers - with what it would discard. */
$remove = static function (string $subject, string $label) use ($orphans, $e): string {
	$action = $orphans[$subject] ?? null;
	if ($action === null) {
		return '';
	}
	$ask = $label . ' ' . $subject . '? ' . $action->describe . ($action->destructive ? '. This discards data.' : '.');
	return '<a class="button" data-on_click="userConfirm" data-action="removeOrphan" data-key="' . $e($action->key())
		. '" data-confirm="' . $e($ask) . '"><i class="fa-regular fa-trash"></i> ' . $e($label) . '</a>';
};
?>
<h1>Database status</h1>
<p class="tagsHint">
	<?= count($state['tables']) ?> tables, <?= (int) $state['withModel'] ?> with a model class · <strong><?= (int) $state['totalDiffs'] ?></strong> differences.
	<span class="schemaLegend schemaActual">database – would change</span>
	<span class="schemaLegend schemaDeclared">model – the reference</span>
</p>
<?php // table filter, only differences and refresh: the header bar (controls.tpl.php) ?>
<?php if ($state['shown'] === []): ?>
	<p class="tagsHint">No table matches the selection.</p>
<?php endif; ?>

<?php
// One table for all of them, a tbody per database table: the browser sizes the
// columns over everything, so they line up from card to card without a width.
// The tbody is the card (white, line, radii - tools.css .schemaCard), an empty
// tbody between two cards is the gap.
if ($state['shown'] !== []): ?>
<div class="schemaScroll"><?php // not .tableScroll: a section holding one turns into a white card (70_components.css) ?>
	<table class="schemaAll">
		<colgroup>
			<col class="schemaColName"><col class="schemaColSide"><col class="schemaColSide"><col class="schemaColAction">
		</colgroup>
		<?php foreach ($state['shown'] as $i => $table): ?>
			<?php if ($i > 0): ?>
				<tbody class="schemaGap" aria-hidden="true"><tr><td colspan="4"></td></tr></tbody>
			<?php endif; ?>
			<tbody class="schemaCard">
				<tr class="schemaCardHead">
					<th colspan="4">
						<div class="schemaCardTitle">
							<h2><?= $e($table->name) ?></h2>
							<span class="logPill"><?= $table->exists ? (int) $table->rowCount . ' rows' : 'not in the database' ?></span>
							<?php if (!$table->hasModel): ?>
								<span class="logPill is-bad">no model class</span>
							<?php elseif ($table->differences > 0): ?>
								<span class="logPill"><?= $e($table->shortClass) ?></span>
								<span class="logPill is-bad"><?= (int) $table->differences ?> difference<?= $table->differences === 1 ? '' : 's' ?></span>
							<?php else: ?>
								<span class="logPill"><?= $e($table->shortClass) ?></span>
								<span class="logPill is-ok">identical</span>
							<?php endif; ?>
							<?= $remove($table->name, 'Remove table') ?>
						</div>
					</th>
				</tr>
				<?php if (!$table->hasModel): ?>
					<tr class="schemaCardNote">
						<td colspan="4">
							No class describes this table.
							<?= isset($orphans[$table->name])
								? 'It sits under a prefix the model uses, so it is orphaned.'
								: 'The audit proposes nothing for it: no primary key, or a prefix no class uses - out of scope, not orphaned.' ?>
						</td>
					</tr>
				<?php endif; ?>
				<tr class="schemaCardCols"><th>Column</th><th>Database</th><th>Model</th><th></th></tr>
				<?php foreach ($table->columns as $column):
					$differs = $column->differs;
					?>
					<tr<?= $differs ? ' class="schemaDiffers"' : '' ?>>
						<td class="schemaName"><?= $e($column->name) ?></td>
						<?= $cell($column->actual, $differs, 'schemaActual') ?>
						<?= $table->hasModel ? $cell($column->declared, $differs, 'schemaDeclared') : '<td class="schemaNothing">—</td>' ?>
						<td class="schemaApply">
							<?= $column->state === StatusState::OnlyInDatabase ? $remove($table->name . '.' . $column->name, 'Remove column') : '' ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		<?php endforeach; ?>
	</table>
</div>
<?php endif; ?>
