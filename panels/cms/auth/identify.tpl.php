<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */

use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\SignInRights;

$me = $this->pendingAccount();
if ($me === null) {
	$this->form = 'login';
	include __DIR__ . '/login.tpl.php';
	return;
}
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$mayPersons = SignInRights::allows($this->scope, $me, SignInRights::PERSONS);
$mayScreens = SignInRights::allows($this->scope, $me, SignInRights::SCREENS);
$screenRows = $mayScreens ? SignInRights::screens($this->scope, $me) : [];
$step = (string) \http::get('as', '');
if ($step === 'person' && !$mayPersons) {
	$step = '';
}
if ($step === 'screen' && $screenRows === []) {
	$step = '';
}
$meFull = trim((string) $me->fullname);
$meLabel = $meFull !== '' ? $meFull : (string) $me->name;
$rows = $step === 'person' ? SignInRights::persons($this->scope, $me) : ($step === 'screen' ? $screenRows : []);
$lead = 'This code can sign in as more than one account.';
if ($step === 'screen') {
	$lead = $rows === [] ? 'There is no screen yet.' : 'Name and description.';
} elseif ($step === 'person') {
	$lead = $rows === [] ? 'There is no other person to sign in as.' : 'Name, full name and email.';
}
$line = static function (array $row) use ($e): string {
	$full = trim((string) ($row['user_fullname'] ?? ''));
	$email = trim((string) ($row['user_email'] ?? ''));
	$bits = [];
	if ($full !== '') {
		$bits[] = $e($full);
	}
	if ($email !== '') {
		$bits[] = $e($email);
	}
	return implode(' · ', $bits);
};
?>
<main class="authSignin">
	<div class="authPane">
		<div class="authBody">
			<h1><?= $step === 'person' ? 'Choose a person' : ($step === 'screen' ? 'Choose a screen' : 'Who is signing in?') ?></h1>
			<p class="authLead"><?= $lead !== '' ? $e($lead) : '&nbsp;' ?></p>
			<div class="authMessages"><?= \message::flush([], true) ?></div>
			<?php if ($step === '') { ?>
				<div class="authMethods">
					<form method="post" action="confirmIdentity">
						<button class="authMethod" type="submit">
							<i class="fa-regular fa-user"></i>
							<span><b>Sign in as yourself</b><small>This device becomes <?= $e($meLabel) ?>.</small></span>
							<i class="fa-regular fa-chevron-right"></i>
						</button>
					</form>
					<?php if ($screenRows !== []) { ?>
						<a class="authMethod ajax" href="?as=screen">
							<i class="fa-regular fa-tv"></i>
							<span><b>Pair a screen</b><small>This device becomes that screen.</small></span>
							<i class="fa-regular fa-chevron-right"></i>
						</a>
					<?php } ?>
					<?php if ($mayPersons) { ?>
						<a class="authMethod ajax" href="?as=person">
							<i class="fa-regular fa-users"></i>
							<span><b>Sign in as another person</b><small>This device becomes that person.</small></span>
							<i class="fa-regular fa-chevron-right"></i>
						</a>
					<?php } ?>
				</div>
			<?php } elseif ($rows !== []) { ?>
				<div class="authPicks">
					<?php foreach ($rows as $row) {
						$id = (int) $row['user_id'];
						$name = trim((string) ($row['user_name'] ?? ''));
						$screen = $step === 'screen';
					?>
						<form class="authPick" method="post" action="confirmIdentity">
							<img src="<?= $e(Portrait::src($id, (string) ($row['user_email'] ?? ''), $screen)) ?>" alt="">
							<span>
								<b><?= $e($name) ?></b>
								<?php $extra = $line($row); if ($extra !== '') { ?><small><?= $extra ?></small><?php } ?>
							</span>
							<input type="hidden" name="<?= $screen ? 'screen' : 'person' ?>" value="<?= $id ?>">
							<button class="button submit icononly" type="submit" aria-label="Sign in"><i class="fa-regular fa-right-to-bracket"></i></button>
						</form>
					<?php } ?>
				</div>
			<?php } ?>
		</div>
	</div>
	<?php if ($step !== '') { ?>
		<p class="authFoot"><a class="authSwitch ajax" href="<?= $e($this->href) ?>"><i class="fa-regular fa-arrow-left"></i><span>Back</span></a></p>
	<?php } else { ?>
		<p class="authFoot"><a class="authSwitch ajax" href="?method=code"><i class="fa-regular fa-arrow-left"></i><span>Scan another code</span></a></p>
	<?php } ?>
</main>
