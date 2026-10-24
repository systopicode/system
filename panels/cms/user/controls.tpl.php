<?php /** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */ ?>
<div class="rightAlign">
	<?php if ($this->state->hijackerId) { ?>
		<form method="post" target="_self" action="<?= htmlspecialchars($this->href . 'ransomUser', ENT_QUOTES) ?>">
			<button class="button" type="submit"><i class="fa-regular fa-arrow-left"></i>Back to my account</button>
		</form>
	<?php } ?>
	<a href="logout" class="ajax button submit"><i class="fa-regular fa-right-from-line"></i>Log out</a>
</div>
