<?php
/** @var \Systopic\System\Tables\Users\Model $passkeyUser */
use Systopic\System\Auth\Passkeys;
use Systopic\System\Auth\PasskeyChallenge;
use Systopic\System\Auth\Session;

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$own = (int) $passkeyUser->id === Session::id();
$canRemove = $own || Session::isSuperuser();
$enabled = Passkeys::available() && $passkeyUser->type !== $passkeyUser::SCREEN;
$keys = $enabled ? \Systopic\System\Queries\Passkeys\Passkeys::ofUser(\Systopic\Db\Scope::default(), (int) $passkeyUser->id, Passkeys::project()) : [];
// Explicit interaction unlocks these fields; account editors must not invite login autofill.
$noAutofill = 'autocomplete="off" data-1p-ignore data-lpignore="true" readonly onpointerdown="this.readOnly=false" onkeydown="this.readOnly=false"';
?>
<section class="widget passkeys" data-passkey-user-id="<?= (int) $passkeyUser->id ?>" data-passkey-endpoint="<?= $escape(\http::$root . 'cms/user/') ?>" data-passkey-origin="<?= $escape(Passkeys::available() ? Passkeys::config()['origin'] : '') ?>" data-passkey-token="<?= $escape(PasskeyChallenge::token()) ?>">
    <header>
        <h2><i class="fa-regular fa-user-key"></i>Passkeys</h2>
        <?php if ($enabled && $keys !== []) { ?><span class="passkeyCount"><?= count($keys) ?></span><?php } ?>
    </header>
    <main>
        <p class="tagsHint">Sign in with your fingerprint, face or device PIN. Your password stays available.</p>
        <div class="cms message passkeyStatus" role="status" aria-live="polite"></div>
        <?php if (!$enabled) { ?>
            <p class="passkeyEmpty"><?= $escape(Passkeys::unavailableReason()) ?></p>
        <?php } else { ?>
            <?php if (!$own) { ?><p class="tagsHint"><?= $canRemove ? 'You can remove passkeys by confirming your own password.' : 'Only the account owner can add or change passkeys.' ?></p><?php } ?>
            <?php if ($keys === []) { ?><p class="passkeyEmpty">No passkeys yet.</p><?php } ?>
            <ul class="passkeyList">
                <?php foreach ($keys as $key) { ?>
                    <li>
                        <details class="passkeyItem" data-passkey-row <?= !$canRemove ? 'data-readonly' : '' ?>>
                            <summary>
                                <?php include __DIR__ . '/fingerprint.tpl.php'; ?>
                                <span class="passkeyItemLabel"><strong><?= $escape($key->name) ?></strong><small>Last used <?= $key->lastUsedAt ? $escape(\Systopic\System\Sys\LocalTime::format($key->lastUsedAt, 'j M Y, H:i')) : 'never' ?></small></span>
                                <?php if ($canRemove) { ?><i class="fa-regular fa-chevron-down" aria-hidden="true"></i><?php } ?>
                            </summary>
                            <?php if ($canRemove) { ?>
                                <div class="passkeyItemFields">
                                    <?php if ($own) { ?><label class="field"><span>Name in this CMS</span><input data-passkey-name maxlength="100" value="<?= $escape($key->name) ?>" <?= $noAutofill ?>></label><?php } ?>
                                    <label class="field"><span>Your current password</span><input type="password" data-passkey-password <?= $noAutofill ?>></label>
                                    <div class="passkeyButtons">
                                        <?php if ($own) { ?><button type="button" class="button submit" data-on_click="passkeyAction" data-passkey-mode="rename" data-key_id="<?= (int) $key->id ?>"><i class="fa-regular fa-check"></i>Save name</button><?php } ?>
                                        <button type="button" class="button" data-on_click="passkeyAction" data-passkey-mode="remove" data-key_id="<?= (int) $key->id ?>"><i class="fa-regular fa-trash"></i>Remove passkey</button>
                                    </div>
                                </div>
                            <?php } ?>
                        </details>
                    </li>
                <?php } ?>
            </ul>
            <?php if ($own) { ?>
                <button type="button" class="button" data-on_click="passkeyAction" data-passkey-mode="setup"><i class="fa-regular fa-plus" aria-hidden="true"></i>Add passkey</button>
                <dialog class="cms cmslightbox passkeySetup" data-step="ready" aria-label="Set up a passkey">
                    <section>
                        <header>
                            <h1 class="passkeySetupTitle">Create a passkey</h1>
                            <ul class="barmenu"><li><a href="#" title="Close" data-on_click="passkeyAction" data-passkey-mode="cancel"><i class="fa-regular fa-xmark"></i></a></li></ul>
                        </header>
                        <main>
                            <div class="passkeyArtwork"><?php include __DIR__ . '/passkey-icons.tpl.php'; ?></div>
                            <p class="passkeySetupLead">A simpler way to sign in. Use your fingerprint, face or device PIN.</p>
                            <div class="passkeySetupFields" data-passkey-row>
                                <label class="field"><span>Passkey name</span><input data-passkey-name maxlength="100" value="<?= $escape(Passkeys::suggestedName()) ?>" <?= $noAutofill ?>></label>
                                <label class="field"><span>Your current password</span><input type="password" data-passkey-password <?= $noAutofill ?>></label>
                                <p class="tagsHint">Your password manager stores the passkey. Your fingerprint or face data stays on your device.</p>
                            </div>
                            <div class="cms message passkeyStatus" role="status" aria-live="polite"></div>
                        </main>
                        <footer class="passkeySetupActions">
                            <button type="button" class="button" data-on_click="passkeyAction" data-passkey-mode="cancel">Cancel</button>
                            <button type="button" class="button submit passkeyContinue" data-on_click="passkeyAction" data-passkey-mode="create"><i class="fa-regular fa-check"></i>Continue</button>
                        </footer>
                    </section>
                </dialog>
            <?php } ?>
        <?php } ?>
    </main>
</section>
