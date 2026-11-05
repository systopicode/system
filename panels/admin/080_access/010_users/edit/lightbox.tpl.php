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
$issued = $this->issuedCode;
if ($issued !== null) {
	$issuedFor = $this->user->type === \Systopic\System\Tables\Users\Model::SCREEN ? 'screen' : 'person';
	include __DIR__ . '/code.tpl.php';
}

