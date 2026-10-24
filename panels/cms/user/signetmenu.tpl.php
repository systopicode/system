<?php
/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */
use Systopic\System\Auth\Portrait;
$id = (int) \Systopic\System\Auth\Session::id();
?>
<div class="signet">
	<span class="initials<?= strlen($this->initials) > 2 ? '' : ' small' ?>"><?= htmlspecialchars($this->initials) ?></span>
	<?php if ($id > 0) { ?>
		<img src="<?= htmlspecialchars(Portrait::src($id, $this->email), ENT_QUOTES) ?>" alt="">
	<?php } ?>
	<a class="ajax" href="<?= htmlspecialchars($this->href, ENT_QUOTES) ?>" title="Account"></a>
</div>
