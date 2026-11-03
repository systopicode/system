<?php
/** @var \Systopic\System\Panels\Root\Admin\Settings\Tags\Panel $this */

use Systopic\System\Queries\TagGroups\TagGroupsPanel;

$this->loadJS();

?>
<h1>Tags</h1>
<p class="tagsHint">Groups organise tags. Merging tags combines their assignments and removes the source tag. Dropping one tag onto another starts a merge; dropping it onto a group moves it.</p>
<?php $this->render("actions-bar.tpl.php"); ?>
<?= \message::flush() ?>
<div class="layout tags">
	<?= implode(TagGroupsPanel::render($this->groups)) ?>
</div>
