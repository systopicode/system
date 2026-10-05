<?php

use Systopic\Db\Connection;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Schema\Status;

Registry::scanTables();
$tables = (new Status(Connection::default()))->tables();

$selected = (string) http::get('table', '');
$onlyDiffs = http::get('diffs', '') === '1';
?>
<div class=leftAlign>
    <?php // .cms input is width:100% — a plain checkbox would take the whole bar
          // and push its label onto the page. The switch is the header's own. ?>
    <form method="get" action="./" onchange="submit()" style="display:flex; align-items:center; gap:16px">
        <select name="table" style="width:180px; margin:0">
            <option value="">All tables</option>
            <?php foreach ($tables as $table): ?>
                <option value="<?= htmlspecialchars($table->name) ?>"
                    <?= $selected === $table->name ? 'selected' : '' ?>>
                    <?= htmlspecialchars($table->name) ?><?= $table->differences > 0 ? ' (' . $table->differences . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label class="checkbox" style="margin:0; align-items:center; gap:8px; white-space:nowrap">
            <span>only differences</span>
            <input type="checkbox" name="diffs" value="1" class="switch" <?= $onlyDiffs ? 'checked' : '' ?>>
            <div><div></div></div>
        </label>
    </form>
</div>

<div class="rightAlign">
    <a href="./<?= http::queryString() ?>" class=submit>&circlearrowleft;</a>
</div>
