<?php
use Systopic\System\Auth\Passkeys;
use Systopic\System\Auth\PasskeyChallenge;
$passkeyEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<h1>Passkey</h1>
<p class="authLead">Next time, sign in without a password.</p>
<main>
    <div class="authMessages"><?= \message::flush([], true) ?></div>
    <div class="passkeys passkeyOnboarding" data-step="ready" data-passkey-endpoint="<?= $passkeyEscape(\http::$root . 'cms/auth/') ?>" data-passkey-origin="<?= $passkeyEscape(Passkeys::config()['origin']) ?>" data-passkey-token="<?= $passkeyEscape(PasskeyChallenge::token()) ?>" data-passkey-name="<?= $passkeyEscape(Passkeys::suggestedName()) ?>">
        <p>Save a passkey in your password manager for this project. Your password will still work, and you can manage passkeys in your account.</p>
        <div class="passkeyArtwork"><?php include dirname(__DIR__, 3) . '/src/Auth/passkey-icons.tpl.php'; ?></div>
        <button type="button" class="submit" data-on_click="passkeyAction" data-passkey-mode="create"><i class="fa-regular fa-user-viewfinder" aria-hidden="true"></i>Add passkey</button>
    </div>
    <p class="authFoot"><a class="authSkip" href="<?= $passkeyEscape(\http::$root . 'cms/auth/continueWithoutPasskey') ?>"><span>Continue without a passkey</span><i class="fa-regular fa-arrow-right"></i></a></p>
</main>
