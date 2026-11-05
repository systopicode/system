<?php /** @var \Systopic\System\Panels\Root\Admin\User\Panel $this */ ?>
<div class="cmsPanelActions">
<div class="rightAlign">
	<?php if ($this->state->hijackerId && (int) $this->state->hijackedId === (int) \Systopic\System\Auth\Session::id()) { ?>
		<form method="post" target="_self" action="<?= htmlspecialchars($this->href . 'ransomUser', ENT_QUOTES) ?>">
			<button class="button" type="submit"><i class="cmsIcon cmsIcon-arrow-left"></i>Back to my account</button>
		</form>
	<?php } ?>
	<a href="<?= htmlspecialchars($this->href . 'logout', ENT_QUOTES) ?>" target="_self" class="button submit"><i class="cmsIcon cmsIcon-right-from-line"></i>Sign out</a>
</div>

</div>
