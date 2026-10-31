<?php
/** Shared two-second sign-in success state; $authSuccessHidden is optional. */
$successProfile = empty($authSuccessHidden) ? \Systopic\System\Auth\SignInFeedback::profile() : [];
$successName = (string) ($successProfile['name'] ?? '');
$successPortrait = (string) ($successProfile['portrait'] ?? '');
?>
<div class="authSuccess"<?= !empty($authSuccessHidden) ? ' data-auth-success hidden' : '' ?>>
    <h1 data-success-title><?= !empty($successProfile['screen']) ? 'Screen successfully paired' : 'Successfully signed in' ?></h1>
    <img class="authSuccessPortrait" data-success-portrait referrerpolicy="no-referrer" alt=""<?= $successPortrait === '' ? ' hidden' : ' src="' . htmlspecialchars($successPortrait, ENT_QUOTES) . '"' ?>>
    <p class="authLead" data-success-name><?= htmlspecialchars(!empty($successProfile['screen']) ? $successName : ($successName !== '' ? 'Welcome, ' . $successName . '!' : 'Welcome!'), ENT_QUOTES) ?></p>
    <?= $authMessages ?? '' ?>
    <div class="authProgress" aria-hidden="true"><span></span></div>
    <p class="authWait">Redirecting in 2 seconds …</p>
</div>
<p class="authFoot" data-success-footer<?= !empty($authSuccessHidden) ? ' hidden' : '' ?>><a class="authSkip" target="_self" data-success-continue href="<?= htmlspecialchars((string) (isset($this) ? ($this->successTarget ?? \http::$root . 'cms/') : \http::$root . 'cms/'), ENT_QUOTES) ?>"><span>Continue now</span><i class="cmsIcon cmsIcon-arrow-right" aria-hidden="true"></i></a></p>
