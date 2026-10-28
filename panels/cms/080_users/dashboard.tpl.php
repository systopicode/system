<?php
/** Dashboard: the last sign-ins, from the login log. @var \Systopic\System\Panels\PanelNode $this */

use Systopic\Db\Scope;
use Systopic\System\Auth\LoginLog;
use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\Presence;
use Systopic\System\Panels\Root\Cms\Panel as Cms;
use Systopic\System\Queries\Devices\Devices;
use Systopic\System\Queries\UserAdmin\UserAdmin;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$onlineIds = Presence::devices();
$tabs = ['recent' => 'Recent'];
$onlineDevices = null;
if ($onlineIds !== null) {
	$tabs['online'] = 'Now online';
	$onlineDevices = [];
	foreach (Devices::active(Scope::default()) as $device) {
		if (isset($onlineIds[(int) $device->id])) {
			$onlineDevices[] = $device;
		}
	}
}
$tab = Cms::widgetTab($this, 'users', $tabs);
?>
<header><h2><i class="fa-regular fa-right-to-bracket"></i>Sign-ins</h2></header>
<?php if (count($tabs) > 1) { ?>
<nav class="dashTabs" role="tablist">
	<?php foreach ($tabs as $key => $label) { ?>
		<button type="button" role="tab" data-on_click="dashTab" data-tab="<?= $key ?>" aria-selected="<?= $key === $tab ? 'true' : 'false' ?>"><?= $label ?></button>
	<?php } ?>
</nav>
<?php } ?>
<?php if ($tab === 'online' && $onlineDevices !== null) {
	$usersById = [];
	foreach (UserAdmin::of(Scope::default())->users as $user) {
		$usersById[(int) $user->id] = $user;
	}
	if ($onlineDevices === []) { ?>
	<main><p class="hint">Nobody is online.</p></main>
	<?php } else { ?>
	<ul class="dashList">
		<?php foreach ($onlineDevices as $device) {
			$user = $usersById[(int) $device->relationId('user')] ?? null;
			$shown = $user === null ? (string) $device->name : (trim((string) $user->fullname) !== '' ? (string) $user->fullname : (string) $user->name);
			$open = $user !== null;
			?>
			<li>
				<<?= $open ? 'a class="ajax" href="' . $e(\http::$root . 'cms/users/users/edit/?user_id=' . (int) $user->id) . '"' : 'div' ?>>
					<?php if ($user !== null) { ?>
						<span class="dashInitials"><img src="<?= $e(Portrait::src((int) $user->id, $user->email)) ?>" alt=""></span>
					<?php } ?>
					<?php [$presence, $presenceTitle] = Presence::deviceMark((int) $device->id); ?>
					<span class="presence<?= $presence !== '' ? ' ' . $presence : '' ?>" title="<?= $presenceTitle ?>"></span>
					<span><strong><?= $e($shown) ?></strong><small><?= $e((string) $device->name) ?></small></span>
				</<?= $open ? 'a' : 'div' ?>>
			</li>
		<?php } ?>
	</ul>
	<?php }
	return;
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
		<?php
		foreach ($signIns as [$date, $name, , , $method]) {
			$user = $users[mb_strtolower($name)] ?? null;
			$shown = $user !== null && trim((string) $user->fullname) !== '' ? (string) $user->fullname : $name;
			[$dot, $dotTitle] = $user === null ? ['', 'Offline'] : Presence::userMark((int) $user->id);
			if ($dot === 'is-online') {
				$dot = 'is-on';
			} elseif ($dot === 'is-offline') {
				$dot = '';
			}
			?>
			<li>
				<div>
					<span class="presence <?= $dot ?>" title="<?= $dotTitle ?>"></span>
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
