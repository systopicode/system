<?php
/** Dashboard: the last sign-ins, from the login log. @var \Systopic\System\Panels\PanelNode $this */

use Systopic\System\Auth\LoginLog;
use Systopic\System\Auth\Portrait;
use Systopic\System\Panels\Root\Cms\Panel as Cms;
use Systopic\System\Queries\UserAdmin\UserAdmin;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$tabs = ['recent' => 'Recent', 'online' => 'Now online'];
$tab = Cms::widgetTab($this, 'users', $tabs);
?>
<header><h2><i class="fa-regular fa-right-to-bracket"></i>Sign-ins</h2></header>
<nav class="dashTabs" role="tablist">
	<?php foreach ($tabs as $key => $label) { ?>
		<button type="button" role="tab" data-on_click="dashTab" data-tab="<?= $key ?>" aria-selected="<?= $key === $tab ? 'true' : 'false' ?>"><?= $label ?></button>
	<?php } ?>
</nav>
<?php if ($tab === 'online') { ?>
	<main><p class="hint">Who is signed in right now will show here. Not available yet.</p></main>
	<?php return;
}
$users = [];
foreach (UserAdmin::of(\Systopic\Db\Scope::default())->users as $user) {
	$users[mb_strtolower((string) $user->name)] = $user;
}
$signIns = array_slice(array_values(array_filter(LoginLog::entries(), static fn(array $parts): bool => $parts[2] === 'logged in')), 0, 5);
?>
<?php if ($signIns === []) { ?>
	<main><p class="hint">No sign-ins logged yet.</p></main>
<?php } else { ?>
	<ul class="dashList">
		<?php foreach ($signIns as [$date, $name, , , $method]) {
			$user = $users[mb_strtolower($name)] ?? null;
			$shown = $user !== null && trim((string) $user->fullname) !== '' ? (string) $user->fullname : $name;
			?>
			<li>
				<div>
					<?php if ($user !== null) { ?>
						<span class="dashInitials"><img src="<?= $e(Portrait::src((int) $user->id, $user->email)) ?>" alt=""></span>
					<?php } else { ?>
						<span class="dashInitials"><?= $e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
					<?php } ?>
					<span><strong><?= $e($shown) ?></strong><small><?= $e(implode(' · ', array_filter([
						Cms::ago(\DateTime::createFromFormat('d.m.Y-H:i:s', $date)?->format('Y-m-d H:i:s')),
						$method !== '' ? LoginLog::methodLabel($method) : '',
					]))) ?></small></span>
				</div>
			</li>
		<?php } ?>
	</ul>
<?php } ?>
<footer><a class="ajax button" href="<?= $e(\http::$root . 'cms/tools/logs/login/') ?>"><i class="fa-regular fa-list"></i><span>Login log</span></a></footer>
