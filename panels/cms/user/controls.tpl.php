<?php /** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */ ?>
<div class='rightAlign'>
	<?php if ($this->state->hijackerId) { ?>
		<div><a class='submit button icononly' href='ransomUser' title='ransom hijacked user'><i class='fas fa-ghost'></i></a></div>
	<?php } ?>
	<a target='_blank' class='button' href='https://fluxo-help.1f2c.de'><i class='fa-light fa-book'></i>Help</a>
	<a href='logout' onclick="this.classList.add('loading')" class='ajax button submit' ><i class='fa-light fa-hand-wave'></i>Logout</a>
</div>
