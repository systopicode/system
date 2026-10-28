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
$passkeyWidget = ($passkeyLayout ?? 'box') === 'widget';
$passkeyLead = 'Sign in with your fingerprint, face or device PIN. Your password stays available.';
$passkeyRow = static function ($key) use ($escape, $canRemove, $own, $noAutofill): void {
	?>
	<li>
		<?php if ($own) { ?>
			<details class="passkeyItem" data-passkey-row>
				<summary>
					<i class="fa-regular fa-user-viewfinder" aria-hidden="true"></i>
					<span class="passkeyItemLabel"><strong><?= $escape($key->name) ?></strong><small>Last used <?= $key->lastUsedAt ? $escape(\Systopic\System\Sys\LocalTime::format($key->lastUsedAt, 'j M Y, H:i')) : 'never' ?></small></span>
					<?php if ($canRemove) { ?><button type="button" class="button transparent icononly" onclick="event.stopPropagation()" data-on_click="passkeyAction" data-passkey-mode="remove" data-key_id="<?= (int) $key->id ?>" aria-label="Remove passkey"><i class="fa-regular fa-trash" aria-hidden="true"></i></button><?php } ?>
					<i class="fa-regular fa-chevron-down" aria-hidden="true"></i>
				</summary>
				<div class="passkeyItemFields">
					<label class="field"><span>Name in this CMS</span><input data-passkey-name maxlength="100" value="<?= $escape($key->name) ?>" <?= $noAutofill ?>></label>
					<div class="passkeyButtons">
						<button type="button" class="button submit" data-on_click="passkeyAction" data-passkey-mode="rename" data-key_id="<?= (int) $key->id ?>"><i class="fa-regular fa-check"></i>Save name</button>
					</div>
				</div>
			</details>
		<?php } else { ?>
			<div class="passkeyItem" data-passkey-row <?= !$canRemove ? 'data-readonly' : '' ?>>
				<div class="passkeyItemHead">
					<i class="fa-regular fa-user-viewfinder" aria-hidden="true"></i>
					<span class="passkeyItemLabel"><strong><?= $escape($key->name) ?></strong><small>Last used <?= $key->lastUsedAt ? $escape(\Systopic\System\Sys\LocalTime::format($key->lastUsedAt, 'j M Y, H:i')) : 'never' ?></small></span>
					<?php if ($canRemove) { ?><button type="button" class="button transparent icononly" data-on_click="passkeyAction" data-passkey-mode="remove" data-key_id="<?= (int) $key->id ?>" aria-label="Remove passkey"><i class="fa-regular fa-trash" aria-hidden="true"></i></button><?php } ?>
				</div>
			</div>
		<?php } ?>
	</li>
	<?php
};
?>
<div class="<?= $passkeyWidget ? 'passkeys' : 'cmsBox passkeys' ?>" data-passkey-user-id="<?= (int) $passkeyUser->id ?>" data-passkey-own="<?= $own ? '1' : '0' ?>" data-passkey-count="<?= count($keys) ?>" data-passkey-grant="<?= (Passkeys::setupPending() || Session::passkeyGrant()) ? '1' : '0' ?>" data-passkey-endpoint="<?= $escape(\http::$root . 'cms/user/') ?>" data-passkey-origin="<?= $escape(Passkeys::available() ? Passkeys::config()['origin'] : '') ?>" data-passkey-token="<?= $escape(PasskeyChallenge::token()) ?>">
    <?php if ($passkeyWidget) { ?>
        <section class="widget">
        <header><h2><i class="fa-regular fa-user-viewfinder" aria-hidden="true"></i>Passkeys</h2></header>
        <main>
            <p class="tagsHint"><?= $escape($passkeyLead) ?></p>
    <?php } else { ?>
        <header>
            <div class="cmsBoxTitles">
                <h2>Passkeys</h2>
                <p class="cmsBoxLead"><?= $escape($passkeyLead) ?></p>
            </div>
        </header>
        <div class="cmsBoxBody">
    <?php } ?>
        <div class="cms message passkeyStatus" role="status" aria-live="polite"></div>
        <?php if (!$enabled) { ?>
            <p class="passkeyEmpty"><?= $escape(Passkeys::unavailableReason()) ?></p>
        <?php } else { ?>
            <?php if (!$own) { ?><p class="tagsHint"><?= $canRemove ? 'You can remove passkeys from this account.' : 'Only the account owner can add or change passkeys.' ?></p><?php } ?>
            <?php if ($keys === []) { ?><p class="passkeyEmpty"><i class="fa-regular fa-user-viewfinder" aria-hidden="true"></i> No passkeys yet.</p><?php } ?>
            <ul class="passkeyList">
                <?php foreach ($keys as $key) { $passkeyRow($key); } ?>
            </ul>
            <?php if (count($keys) > 3) { ?>
                <?php if ($passkeyWidget) { ?>
                    <button type="button" class="cmsBoxMore" data-open-box=".passkeyListBox">Show all passkeys (<?= count($keys) ?>)</button>
                <?php } else { ?>
                    <button type="button" class="cmsBoxMore" onclick="this.closest('.passkeys').classList.add('is-all');this.remove()">Show all passkeys (<?= count($keys) ?>) <i class="fa-regular fa-chevron-down" aria-hidden="true"></i></button>
                <?php } ?>
            <?php } ?>
            <?php if ($own && !$passkeyWidget) { ?>
                <dialog class="cms cmslightbox passkeySetup" data-step="ready" aria-label="Set up a passkey">
                    <section>
                        <header>
                            <h1 class="passkeySetupTitle">Create a passkey</h1>
                            <ul class="barmenu"><li><a href="#" aria-label="Close" data-on_click="passkeyAction" data-passkey-mode="cancel"><i class="fa-regular fa-xmark"></i></a></li></ul>
                        </header>
                        <main>
                            <div class="passkeyArtwork"><?php include __DIR__ . '/passkey-icons.tpl.php'; ?></div>
                            <p class="passkeySetupLead">A simpler way to sign in. Use your fingerprint, face or device PIN.</p>
                            <div class="passkeySetupFields" data-passkey-row>
                                <label class="field"><span>Passkey name</span><input data-passkey-name maxlength="100" value="<?= $escape(Passkeys::suggestedName()) ?>" <?= $noAutofill ?>></label>
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
    <?php if ($passkeyWidget) { ?>
        </main>
        <?php if ($enabled && $own) { ?>
            <footer><button type="button" class="button submit" data-on_click="passkeyAction" data-passkey-mode="setup"><i class="fa-regular fa-user-key" aria-hidden="true"></i> Add passkey</button></footer>
        <?php } ?>
        </section>
        <?php if ($enabled && $own) { ?>
            <dialog class="cms cmslightbox passkeySetup" data-step="ready" aria-label="Set up a passkey">
                <section>
                    <header>
                        <h1 class="passkeySetupTitle">Create a passkey</h1>
                        <ul class="barmenu"><li><a href="#" aria-label="Close" data-on_click="passkeyAction" data-passkey-mode="cancel"><i class="fa-regular fa-xmark"></i></a></li></ul>
                    </header>
                    <main>
                        <div class="passkeyArtwork"><?php include __DIR__ . '/passkey-icons.tpl.php'; ?></div>
                        <p class="passkeySetupLead">A simpler way to sign in. Use your fingerprint, face or device PIN.</p>
                        <div class="passkeySetupFields" data-passkey-row>
                            <label class="field"><span>Passkey name</span><input data-passkey-name maxlength="100" value="<?= $escape(Passkeys::suggestedName()) ?>" <?= $noAutofill ?>></label>
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
        <?php if ($passkeyWidget && count($keys) > 3) { ?>
            <div class="cms cmslightbox passkeyListBox" hidden>
                <section>
                    <header>
                        <h1>Passkeys</h1>
                        <ul class="barmenu"><li><button type="button" data-box-close aria-label="Close"><i class="fa-regular fa-xmark"></i></button></li></ul>
                    </header>
                    <main>
                        <ul class="passkeyList">
                            <?php foreach ($keys as $key) { $passkeyRow($key); } ?>
                        </ul>
                    </main>
                </section>
            </div>
        <?php } ?>
    <?php } else { ?>
        <?php if ($enabled && $own) { ?>
			<div class="cmsBoxActions"><button type="button" class="button submit" data-on_click="passkeyAction" data-passkey-mode="setup"><i class="fa-regular fa-user-key" aria-hidden="true"></i> Add passkey</button></div>
        <?php } ?>
        </div>
    <?php } ?>
</div>
