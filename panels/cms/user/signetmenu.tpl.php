<?php
/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */
$gravatar = $this->get_gravatar($this->email);
?>
<div class="signet" data-on_click="<?= \http::$root ?>cms/user/" data-method="get">
	<span class="initials<?= strlen($this->initials) > 2 ? '' : ' small' ?>"><?= htmlspecialchars($this->initials) ?></span>
	<?php if ($gravatar) { ?>
		<img class='gravatr' src='<?= $gravatar ?>'>
	<?php } ?>
</div>
