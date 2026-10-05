<?php

/**
 * Database against model, side by side.
 *
 * The database is on the left, what the class declares is on the right. The
 * colours follow from that: **orange on the left** is what would change,
 * **green on the right** is the reference it would change towards. A row
 * without colour is a row nobody has to think about.
 *
 * Compared through `Column::comparable()` — the same rule the audit uses. So
 * this view cannot show a row as matching that the audit then offers to
 * change.
 *
 * This page changes nothing. It only reads.
 */

use Systopic\Db\Connection;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Schema\Status;

Registry::scanTables();

$status = new Status(Connection::default());
$tables = $status->tables();

$onlyDiffs = http::get('diffs', '') === '1';
$filter    = (string) http::get('table', '');

$shown = array_values(array_filter($tables, static function ($t) use ($onlyDiffs, $filter) {
    if ($filter !== '' && $t->name !== $filter) {
        return false;
    }
    return !$onlyDiffs || $t->differences > 0;
}));

$totalDiffs = array_sum(array_map(static fn($t) => $t->differences, $tables));
$withModel  = count(array_filter($tables, static fn($t) => $t->hasModel));

/** One cell showing one side of a column. */
$cell = static function (?string $text, string $class): string {
    return $text === null
        ? "<td class='schemaNothing'>&mdash;</td>"
        : "<td class='$class'>" . htmlspecialchars($text, ENT_QUOTES) . '</td>';
};
?>
<style>
    .schemaStatus { width: 100%; border-collapse: collapse; }
    .schemaStatus > tbody > tr > td { vertical-align: top; padding: 0 12px 24px 0; width: 50%; }
    .schemaStatus h2 { margin: 0 0 4px; font-size: 1.05em; }
    .schemaStatus h2 small { font-weight: normal; opacity: .6; }
    .schemaSide { width: 100%; border-collapse: collapse; font-family: monospace; font-size: .85em; }
    .schemaSide th { text-align: left; padding: 2px 8px 2px 0; opacity: .55; font-weight: normal; }
    .schemaSide td { padding: 2px 8px 2px 0; white-space: nowrap; }
    .schemaSide tr.differs td.schemaName { font-weight: bold; }
    /* orange = would change, green = the reference for it */
    .schemaActual   { background: #ffe2c2; }
    .schemaDeclared { background: #d3f2d0; }
    .schemaNothing  { opacity: .35; }
    .schemaNoModel  { opacity: .5; }
    .schemaLegend span { padding: 2px 8px; margin-right: 8px; font-family: monospace; font-size: .85em; }
</style>

<p class="schemaLegend">
    <?= count($tables) ?> tables, <?= $withModel ?> with a model class &middot;
    <b><?= $totalDiffs ?></b> differences
    &nbsp; <span class="schemaActual">database &mdash; would change</span>
    <span class="schemaDeclared">model &mdash; the reference</span>
</p>

<table class="schemaStatus">
    <?php foreach ($shown as $table): ?>
        <tr>
            <td>
                <h2><?= htmlspecialchars($table->name) ?>
                    <small><?= $table->exists
                        ? $table->rowCount . ' rows'
                        : 'not in the database' ?></small></h2>
                <table class="schemaSide">
                    <tr><th>Column</th><th>Database</th></tr>
                    <?php foreach ($table->columns as $column): ?>
                        <tr class="<?= $column->differs ? 'differs' : '' ?>">
                            <td class="schemaName"><?= htmlspecialchars($column->name) ?></td>
                            <?= $cell($column->actual, $column->differs ? 'schemaActual' : '') ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </td>
            <td>
                <h2><?= $table->hasModel
                        ? htmlspecialchars($table->shortClass)
                        : '<i>no model class</i>' ?>
                    <small><?= $table->differences === 0
                        ? ($table->hasModel ? 'identical' : '')
                        : $table->differences . ' differences' ?></small></h2>
                <?php if (!$table->hasModel): ?>
                    <p class="schemaNoModel">
                        No class describes this table. That is not the same as orphaned:
                        a junction table with a composite primary key cannot be expressed
                        by this layer, which is why the audit proposes nothing for it
                        either.
                    </p>
                <?php else: ?>
                    <table class="schemaSide">
                        <tr><th>Column</th><th>Model</th></tr>
                        <?php foreach ($table->columns as $column): ?>
                            <tr class="<?= $column->differs ? 'differs' : '' ?>">
                                <td class="schemaName"><?= htmlspecialchars($column->name) ?></td>
                                <?= $cell($column->declared, $column->differs ? 'schemaDeclared' : '') ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php if ($shown === []): ?>
    <p>No table matches the selection.</p>
<?php endif; ?>
