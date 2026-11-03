<?php
/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */
use Systopic\System\Auth\Portrait;
$id = (int) \Systopic\System\Auth\Session::id();
$identity = \Systopic\System\Auth\Session::current();
$name = trim((string) $identity->fullname) !== '' ? (string) $identity->fullname : (string) $identity->name;
?>
<a class="ajax cmsAccount" href="<?= htmlspecialchars($this->href, ENT_QUOTES) ?>" title="Account">
	<div class="signet">
		<span class="initials<?= strlen($this->initials) > 2 ? '' : ' small' ?>"><?= htmlspecialchars($this->initials) ?></span>
		<?php if ($id > 0) { ?>
			<img referrerpolicy="no-referrer" src="<?= htmlspecialchars(Portrait::src($id, $this->email, ($identity->user->type ?? '') === \Systopic\System\Tables\Users\Model::SCREEN), ENT_QUOTES) ?>" alt="">
		<?php } ?>
		<i class="cmsIcon cmsIcon-gear cmsAccountGear" aria-hidden="true"></i>
	</div>
	<span class="cmsAccountName"><?= htmlspecialchars($name, ENT_QUOTES) ?></span>
</a>
