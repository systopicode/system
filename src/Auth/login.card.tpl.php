<?php
/**
 * The one sign-in card: invitation code, another device, passkey, password, or the method list.
 *
 * The caller sets the variables. Missing ones fall back to a quiet default
 * so a project can include this file without copying the markup.
 *
 * @var string $authMethod '' | code | device | passkey | password | signedIn
 * @var bool $authPasskey
 * @var bool $authCodes
 * @var array{code: string, expires: int, redirect?: string}|null $authPair
 * @var string $authPairUrl
 * @var string $authUsername
 * @var string $authCode
 * @var bool $authAuto
 * @var string $authNext
 * @var string|null $authForgot
 * @var string|null $authRegister
 * @var string $authForgotClass
 * @var string $authEndpoint
 */

$authMethod = $authMethod ?? '';
$authPasskey = $authPasskey ?? false;
$authCodes = $authCodes ?? false;
$authPair = $authPair ?? null;
$authPairUrl = $authPairUrl ?? '';
$authUsername = $authUsername ?? '';
$authCode = $authCode ?? '';
$authAuto = $authAuto ?? false;
$authNext = $authNext ?? '';
$authForgot = $authForgot ?? null;
$authRegister = $authRegister ?? null;
$authForgotClass = $authForgotClass ?? '';
$authEndpoint = $authEndpoint ?? '';
$authProfile = $authProfile ?? (\http::$root . 'cms/user/');
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$authMessages = '<div class="authMessages">' . \message::flush([], true) . '</div>';

$passkeyBox = '';
$origin = '';
if ($authPasskey && $authEndpoint !== '') {
    \http::sessionStartOnce();
    try {
        $origin = \Systopic\System\Auth\Passkeys::config()['origin'];
    } catch (\DomainException) {
        $origin = '';
    }
    $quiet = $authMethod === 'password' ? ' passkeyLogin--quiet' : '';
    ob_start();
    include __DIR__ . '/passkey-icons.tpl.php';
    $passkeyIcons = ob_get_clean();
    $passkeyBox = '<div class="passkeys passkeyLogin' . $quiet . '" data-passkey-endpoint="' . $e($authEndpoint) . '" data-passkey-origin="' . $e($origin) . '" data-passkey-token="' . $e(\Systopic\System\Auth\PasskeyChallenge::token()) . '"' . ($authMethod === 'passkey' ? ' data-passkey-auto="1"' : '') . '>'
        . '<button type="button" class="button submit passkeyLoginButton" data-on_click="passkeyAction" data-passkey-mode="login"><i class="cmsIcon cmsIcon-user-viewfinder"></i> Use your passkey</button>'
        . '<div class="passkeyAuthFeedback" hidden><div class="passkeyArtwork">' . $passkeyIcons . '</div></div>'
        . '</div>';
}

$qr = '';
if ($authMethod === 'device' && is_array($authPair) && $authPairUrl !== '' && class_exists('qr')) {
    $qr = \qr::svgMarkup(\qr::payload('url', ['url' => $authPairUrl]), 4, 'M', 'qr-code');
}

$switch = '<p class="authFoot"><a class="button transparent authSwitch ajax" href="?method=choose"><i class="cmsIcon cmsIcon-arrow-left"></i><span>Use another method</span></a></p>';
$showSwitch = ($authMethod === 'passkey' && $authPasskey)
    || (in_array($authMethod, ['device', 'code'], true) && $authCodes)
    || $authMethod === 'password';
$codeChars = str_split(substr(strtoupper((string) preg_replace('/[^0-9A-Z]/', '', $authCode)), 0, 8));
?>
<main class="authSignin">
    <?php if ($authMethod === 'signedIn') { ?>
        <?php $authSuccessHidden = false; include __DIR__ . '/signed-in.tpl.php'; ?>
    <?php } else { ?>
    <div class="authPane">
    <?php if ($authMethod === 'passkey' && $authPasskey) { ?>
        <div class="passkeys passkeyLogin" data-passkey-endpoint="<?= $e($authEndpoint) ?>" data-passkey-origin="<?= $e($origin ?? '') ?>" data-passkey-token="<?= $e(\Systopic\System\Auth\PasskeyChallenge::token()) ?>" data-passkey-auto="1">
            <div class="passkeyAuthFeedback"><div class="passkeyArtwork"><?php include __DIR__ . '/passkey-icons.tpl.php'; ?></div></div>
            <h1>Use your passkey</h1>
            <p class="authLead">Sign in with Face ID, Touch ID or your security key.</p>
            <?= $authMessages ?>
            <button type="button" class="button submit passkeyLoginButton" data-on_click="passkeyAction" data-passkey-mode="login"><i class="cmsIcon cmsIcon-user-viewfinder"></i> Use your passkey</button>
        </div>
    <?php } elseif ($authMethod === 'device' && $authCodes) { ?>
        <div class="authBody">
            <h1><?= $e($authPairTitle ?? 'Remote login') ?></h1>
            <?= $authMessages ?>
            <?php if ($qr !== '') { ?>
                <div class="authQrWrap" data-screen-pairing data-code-until="<?= (int) ($authPair['expires'] ?? 0) ?>">
                    <div data-code-live>
                        <div class="authQr"><?= $qr ?></div>
                        <p class="authCodeLine"><?= $e($authPair['code'] ?? '') ?></p>
                        <p class="authLead">Valid for 10 minutes. <?= $e($authPairLead ?? 'Scan this in the account on a device that is already signed in, or open the link there.') ?></p>
                    </div>
                    <div class="cms message" data-code-expired hidden><div class="warning"><p>The key has expired. Reload the page for a new one.</p></div></div>
                </div>
            <?php } else { ?>
                <p class="authNote">A code is not ready yet. Reload this page, or use another method.</p>
            <?php } ?>
        </div>
    <?php } elseif ($authMethod === 'code' && $authCodes) { ?>
        <div class="authBody">
            <h1>Sign-in code</h1>
            <?= $authMessages ?>
            <div class="qrScan" data-qr-host="login" data-profile="<?= $e($authProfile) ?>" hidden>
                <div class="qrScanStage">
                    <video playsinline muted autoplay disablepictureinpicture disableremoteplayback></video>
                    <div class="qrScanFrame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                    <button type="button" class="qrScanTorch" data-qr-torch hidden aria-label="Light"><i class="cmsIcon cmsIcon-bolt"></i></button>
                </div>
                <p class="qrScanHint" data-qr-hint>Hold the QR code steady and well lit in the frame.</p>
                <button type="button" class="button authSkip" data-qr-fields><i class="cmsIcon cmsIcon-keyboard"></i><span>Type the code</span></button>
                <div class="cms message qrScanMsg" data-qr-msg hidden></div>
                <div class="qrScanDim" data-qr-dim hidden>
                    <strong data-qr-dim-title>Scanning timed out</strong>
                    <p data-qr-dim-text></p>
                    <button type="button" class="button submit" data-qr-again><i class="cmsIcon cmsIcon-arrows-rotate"></i> <span data-qr-label>Try again</span></button>
                </div>
            </div>
            <form class="authCodeForm appLoginForm" data-code-form method="post" action="redeemCode" <?= $authAuto ? 'data-auto-submit' : '' ?> onsubmit='this.classList.add("loading")'>
                <div class="authCodeSlots" data-code-slots>
                    <?php for ($slot = 0; $slot < 8; $slot++) {
                        if ($slot === 4) {
                            echo '<span class="authCodeDash" aria-hidden="true">-</span>';
                        }
                        $filled = $codeChars[$slot] ?? '';
                    ?>
                    <input data-slot="<?= $slot ?>" inputmode="text" autocapitalize="characters" spellcheck="false" autocomplete="<?= $slot === 0 ? 'one-time-code' : 'off' ?>" aria-label="Character <?= $slot + 1 ?>" value="<?= $e($filled) ?>">
                    <?php } ?>
                    <input type="hidden" name="code" value="<?= $e(implode('', array_slice($codeChars, 0, 4)) . (count($codeChars) > 4 ? '-' . implode('', array_slice($codeChars, 4)) : '')) ?>">
                </div>
                <p class="authError" id="appCodeError" hidden></p>
                <button class="button submit" type="submit"><i class="cmsIcon cmsIcon-right-to-bracket"></i> Sign in</button>
                <button type="button" class="button authScan" data-qr-start><i class="cmsIcon cmsIcon-camera"></i> Scan QR code</button>
            </form>
            <script>
                (function () {
                    // A failed attempt stays in the manual form, even if incomplete or empty.
                    if (<?= !empty($authCodeAttempted) ? 'true' : 'false' ?>) return;
                    var desktop = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
                    if (desktop) return;
                    var filled = document.querySelector('[data-code-form] input[name="code"]');
                    if (filled && String(filled.value).replace(/[^0-9A-Za-z]/g, '').length >= 8) return;
                    var host = document.querySelector('[data-qr-host="login"]');
                    if (!host || host.getAttribute('data-qr-boot')) return;
                    host.setAttribute('data-qr-boot', '1');
                    var button = host.parentElement && host.parentElement.querySelector('[data-qr-start]');
                    if (button) button.click();
                })();
            </script>
        </div>
    <?php } elseif ($authMethod === 'password') { ?>
        <h1>Sign in</h1>
        <p class="authLead">&nbsp;</p>
        <?= $authMessages ?>
        <?= $passkeyBox ?>
        <form class="appLoginForm" method="post" action="doLogin" onsubmit='this.classList.add("loading")'>
            <input type="hidden" name="passkey_supported" value="0">
            <?php if ($authNext !== '') { ?>
                <input type="hidden" name="next" value="<?= $e($authNext) ?>">
            <?php } ?>
            <div class="inputGroup"><i class="cmsIcon cmsIcon-user"></i>
                <input required spellcheck="false" placeholder="Username or email" autocapitalize="off" type="text" autocomplete="username webauthn" name="username" value="<?= $e($authUsername) ?>">
                <span class="bar"></span>
            </div>
            <div class="inputGroup"><i class="cmsIcon cmsIcon-lock"></i>
                <input required spellcheck="false" placeholder="Password" type="password" name="password" autocomplete="current-password" autocapitalize="off" value="<?= $e($authPassword ?? '') ?>">
                <button type="button" class="authReveal" aria-label="Show password" onclick="var i=this.parentElement.querySelector('input');var show=i.type==='password';i.type=show?'text':'password';this.setAttribute('aria-label',show?'Hide password':'Show password');this.querySelector('i').className=show?'cmsIcon cmsIcon-eye-slash':'cmsIcon cmsIcon-eye';"><i class="cmsIcon cmsIcon-eye"></i></button>
                <span class="bar"></span>
            </div>
            <p class="authError" id="appLoginError" hidden></p>
            <button class="button submit authPasswordButton" type="submit"><i class="cmsIcon cmsIcon-right-to-bracket"></i> Sign in</button>
        <?php if ($authForgot !== null || $authRegister !== null) { ?>
            <p class="authLinks">
                <?php if ($authForgot !== null) { ?><a href="<?= $e($authForgot) ?>" class="<?= $e($authForgotClass) ?>">Forgot your password?</a><?php } ?>
            </p>
        <?php } ?>
        </form>
    <?php } else { ?>
        <h1>Sign in</h1>
        <p class="authLead">&nbsp;</p>
        <?= $authMessages ?>
        <div class="authMethods">
            <?php if ($authPasskey) { ?>
                <a class="authMethod ajax" href="?method=passkey">
                    <i class="cmsIcon cmsIcon-user-viewfinder"></i>
                    <span><b>Passkey</b><small>Face ID, Touch ID or a security key.</small></span>
                    <i class="cmsIcon cmsIcon-chevron-right"></i>
                </a>
            <?php } ?>
            <?php if ($authCodes) { ?>
                <a class="authMethod ajax" href="?method=code">
                    <i class="cmsIcon cmsIcon-input-numeric"></i>
                    <span><b>Sign-in code</b><small>Use a code from an admin.</small></span>
                    <i class="cmsIcon cmsIcon-chevron-right"></i>
                </a>
                <a class="authMethod ajax" href="?method=device">
                    <i class="cmsIcon cmsIcon-qrcode"></i>
                    <span><b>Remote login</b><small>Use a device that is already signed in.</small></span>
                    <i class="cmsIcon cmsIcon-chevron-right"></i>
                </a>
            <?php } ?>
            <a class="authMethod ajax" href="?method=password">
                <i class="cmsIcon cmsIcon-input-password"></i>
                <span><b>Username &amp; password</b><small>Username and password.</small></span>
                <i class="cmsIcon cmsIcon-chevron-right"></i>
            </a>
        </div>
    <?php } ?>
    </div>
    <?php } ?>
    <?php if ($showSwitch) { echo $switch; } ?>
    <?php if ($authMethod !== 'signedIn') { ?>
    <?php $authSuccessHidden = true; include __DIR__ . '/signed-in.tpl.php'; ?>
    <?php } ?>
</main>
