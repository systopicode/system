<?php
/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Panel $this */

use Systopic\System\Queries\TagGroups\TagGroupsPanel;

$this->loadJS();
?>
<h1>Manage Tags & Groups</h1>
<?= \message::flush() ?>
<div class="layout tags">
	<?= implode(TagGroupsPanel::render($this->groups)) ?>
</div>
