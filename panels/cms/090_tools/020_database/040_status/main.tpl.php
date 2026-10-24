<?php

/**
 * Database against model. Each difference is database, then the model.
 * Apply writes the pending column changes for that table.
 *
 * @var \Systopic\System\Panels\Root\Cms\Tools\Database\Status\Panel $this
 */

$state = $this->schemaState();
$differ = $state['differ'];
$agree = $state['agree'];

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$show = static function (?string $text, string $empty) use ($e): string {
	return $text === null || $text === '' ? $empty : $e($text);
};
?>
<h1>Database status</h1>
<p class="tagsHint"><?= count($state['tables']) ?> tables, <?= (int) $state['withModel'] ?> with a model. <?= (int) $state['totalDiffs'] ?> columns would change.</p>
<div class="schemaBar">
	<form class="schemaFilters" method="get" action="./">
		<select name="table" class="onchangesubmit">
			<option value="">All tables</option>
			<?php foreach ($state['tables'] as $table): ?>
				<option value="<?= $e($table->name) ?>"<?= $state['filter'] === $table->name ? ' selected' : '' ?>><?= $e($table->name) ?><?= $table->differences > 0 ? ' (' . (int) $table->differences . ')' : '' ?></option>
			<?php endforeach; ?>
		</select>
		<label class="checkbox">
			<span>Only differences</span>
			<input type="checkbox" name="diffs" value="1" class="switch onchangesubmit"<?= $state['onlyDiffs'] ? ' checked' : '' ?>>
			<div><div></div></div>
		</label>
	</form>
	<a class="button" data-on_click="refreshStatus">Refresh</a>
</div>
<?php if ($state['shown'] === []): ?>
	<p class="tagsHint">No table matches the selection.</p>
<?php endif; ?>

<?php foreach ($differ as $table):
	$rows = $table->exists ? $table->rowCount . ' rows' : 'not in the database';
	$diffColumns = array_values(array_filter($table->columns, static fn($column) => $column->differs));
	?>
	<section class="widget is-list">
		<header>
			<h2><?= $e($table->name) ?></h2>
			<?php if (!$table->hasModel): ?>
				<span class="logPill">No model</span>
			<?php else: ?>
				<span class="logPill is-bad"><?= (int) $table->differences ?> differences</span>
			<?php endif; ?>
		</header>
		<?php if (!$table->hasModel): ?>
			<main><p class="tagsHint"><?= $e($rows) ?>. Nothing is proposed for this table.</p></main>
		<?php else: ?>
			<?php $same = count($table->columns) - count($diffColumns); ?>
			<p class="tagsHint"><?= $e($table->shortClass) ?> · <?= $e($rows) ?>. <?= $same === 1 ? '1 column already agrees' : $same . ' columns already agree' ?>.</p>
			<table>
				<thead>
					<tr><th>Column</th><th>Database → model</th><th></th></tr>
				</thead>
				<tbody>
					<?php foreach ($diffColumns as $column): ?>
						<tr class="schemaDiff">
							<td><?= $e($column->name) ?></td>
							<td>
								<span class="schemaCompare">
									<span class="schemaFrom"><?= $show($column->actual, 'missing') ?></span>
									<i class="fa-regular fa-arrow-right"></i>
									<span class="schemaTo"><?= $show($column->declared, 'not declared') ?></span>
								</span>
							</td>
							<td class="schemaApply">
								<?php if ($table->exists): ?>
									<a class="button submit" data-on_click="userConfirm" data-action="applyTable" data-table="<?= $e($table->name) ?>" data-column="<?= $e($column->name) ?>" data-confirm="<?= $e('Apply the model to ' . $table->name . '.' . $column->name . '? The column can change.') ?>">Apply</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<footer>
				<a class="button submit" data-on_click="userConfirm" data-action="applyTable" data-table="<?= $e($table->name) ?>" data-confirm="<?= $e($table->exists ? 'Apply the model to ' . $table->name . '? Columns can change.' : 'Create ' . $table->name . ' from the model?') ?>">Apply all</a>
			</footer>
		<?php endif; ?>
	</section>
<?php endforeach; ?>

<?php if ($agree !== []): ?>
	<section class="widget is-list">
		<header><h2>In agreement</h2><span class="logPill is-ok"><?= count($agree) ?></span></header>
		<table>
			<thead>
				<tr><th>Table</th><th>Rows</th><th>Model</th></tr>
			</thead>
			<tbody>
				<?php foreach ($agree as $table): ?>
					<tr>
						<td><?= $e($table->name) ?></td>
						<td class="muted"><?= $table->exists ? (int) $table->rowCount : '—' ?></td>
						<td class="muted"><?= $e($table->shortClass) ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>
<?php endif; ?>
