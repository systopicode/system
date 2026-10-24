<?php
/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Panel $this */

use Systopic\System\Queries\TagGroups\TagGroupsPanel;

$this->loadJS();
?>
<h1>Tags</h1>
<p class="tagsHint">Drag a tag to change its order. Drop it on another tag to join them, or on a group to move it.</p>
<?= \message::flush() ?>
<div class="layout tags">
	<?= implode(TagGroupsPanel::render($this->groups)) ?>
</div>
