<?php /** @var \Systopic\System\Panels\Root\Admin\Access\Users\Edit\Panel $this */
if (!$this->user) {
	return;
}
?>
<div class="cms cmslightbox userEdit" data-size="2">
	<section>
		<?php
		if ($this->user) {
			$this->withView('user');
		}
		?>
	</section>
</div>
<?php
$fixedUser = $this->user;
$fixedMode = 'hidden';
$fixedClose = null;
$fixedRefresh = $this->href . 'refreshDevices';
include dirname(__DIR__, 3) . '/user/fixedDialogs.tpl.php';
?>
