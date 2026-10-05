<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */

$group = $this->group;
?>
<header>
	<h1>Edit Content Group</h1>
	<?= \menu::create('barmenu')->link('../')->icon('far fa-times') ?>
</header>
<main>
	<div class="layout">
		<div data-group_id="<?= $group->id ?>">
			<?php
			echo \domEditable::text('h2')->doc('name')->value((string) $group->name)->onconfirm('updateGroupName');
			echo \domEditable::text('p')->doc('description')->value((string) $group->description)->onconfirm('updateGroupDescription');
			echo \html::create('input[type=color]')->name('color')->value((string) $group->color)->change('updateGroupColor');
			?>
			<i class="far fa-times" data-on_click="resetGroupColor"></i>
		</div>
	</div>
</main>
