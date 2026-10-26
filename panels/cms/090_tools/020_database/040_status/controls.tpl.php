<?php
/**
 * Header bar of the database status: which table, only differences, refresh.
 *
 * @var \Systopic\System\Panels\Root\Cms\Tools\Database\Status\Panel $this
 */
$state = $this->schemaState();
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<div class="leftAlign">
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
</div>
<div class="rightAlign">
	<a class="button" data-on_click="refreshStatus"><i class="fa-regular fa-arrows-rotate"></i><span>Refresh</span></a>
</div>
