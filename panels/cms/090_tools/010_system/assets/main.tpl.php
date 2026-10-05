<h2>Assets</h2>
<p>
	JS, CSS, images and fonts of the system, the packages and the project's own
	<code>lib/</code>, <code>panels/</code> and <code>site/</code> - for a
	<code>public/</code> that cannot reach them, they go out through
	<code>/assets/</code> (config key <code>assets</code> of the instance).
	In mode <b>publish</b> every file is copied to <code>public/assets/</code> on first
	use and renewed when its original is newer; fonts and images a stylesheet loads
	are only renewed by publishing everything.
</p>
<div class="assetsStatus"><?= include __DIR__ . '/status.php' ?></div>
<br>
<a class="button put" href="publishAll">Publish all Assets</a>
<a class="button" data-on_click="userConfirm" href="clearAll"
   data-confirm="Remove all published copies? They are made again on first use.">Clear all Assets</a>
<style>
	table.assetsStatusTable th {
		text-align: left;
		padding-right: 20px;
		vertical-align: top;
		white-space: nowrap;
	}

	table.assetsStatusTable ul {
		margin: 0;
		padding-left: 0;
		list-style: none;
	}
</style>
