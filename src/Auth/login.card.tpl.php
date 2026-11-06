<?php
/**
 * The one sign-in card: invitation code, another device, passkey, password, or the method list.
 *
 * The caller sets the variables. Missing ones fall back to a quiet default
 * so a project can include this file without copying the markup.
 *
 * @var string $authMethod '' | code | device_code | passkey | password | signedIn
 * @var bool $authPasskey
 * @var bool $authCodes
 * @var array{code: string, expires: int, redirect?: string}|null $authPair
 * @var string $authPairUrl
 * @var string $authUsername
 * @var string $authCode
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
$authNext = $authNext ?? '';
$authForgot = $authForgot ?? null;
$authRegister = $authRegister ?? null;
$authForgotClass = $authForgotClass ?? '';
$authEndpoint = $authEndpoint ?? '';
$authProfile = $authProfile ?? (\http::$root . 'admin/user/');
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
        . '<button type="button" class="button submit passkeyLoginButton" data-on_click="passkeyAction" data-passkey-mode="login"><i class="cmsIcon cmsIcon-user-viewfinder"></i> <span data-passkey-label>Use your passkey</span></button>'
        . '<div class="passkeyAuthFeedback" hidden><div class="passkeyArtwork">' . $passkeyIcons . '</div></div>'
        . '</div>';
}

$qr = '';
if ($authMethod === 'device_code' && is_array($authPair) && $authPairUrl !== '' && class_exists('qr')) {
    $qr = \qr::svgMarkup(\qr::payload('url', ['url' => $authPairUrl]), 4, 'M', 'qr-code');
}

$switch = '<p class="authFoot"><a class="button transparent authSwitch ajax" href="?method=choose"><i class="cmsIcon cmsIcon-arrow-left"></i><span>Use another method</span></a></p>';
$showSwitch = ($authMethod === 'passkey' && $authPasskey)
    || (in_array($authMethod, ['device_code', 'code'], true) && $authCodes)
    || $authMethod === 'password';
$codeChars = str_split(substr(strtoupper((string) preg_replace('/[^0-9A-Z]/', '', $authCode)), 0, 8));
?>
<main class="authSignin" data-auth-method="<?= $e($authMethod ?: 'choose') ?>">
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
            <button type="button" class="button submit passkeyLoginButton" data-on_click="passkeyAction" data-passkey-mode="login"><i class="cmsIcon cmsIcon-user-viewfinder"></i> <span data-passkey-label>Use your passkey</span></button>
        </div>
    <?php } elseif ($authMethod === 'device_code' && $authCodes) { ?>
        <div class="authBody">
            <h1>Sign in with another device</h1>
            <?= $authMessages ?>
            <?php if ($qr !== '') { ?>
                <?php include __DIR__ . '/device-code.tpl.php'; ?>
            <?php } else { ?>
                <p class="authNote">A code is not ready yet. Reload this page, or use another method.</p>
            <?php } ?>
        </div>
    <?php } elseif ($authMethod === 'code' && $authCodes) { ?>
        <div class="authBody">
            <h1>Scan or enter code</h1>
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
            <form class="authCodeForm appLoginForm" data-code-form method="post" action="redeemCode" onsubmit='this.classList.add("loading")'>
                <input type="hidden" name="passkey_supported" value="0">
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
                <button type="button" class="button authScan" data-qr-start><i class="cmsIcon cmsIcon-object-scan"></i> Scan QR code</button>
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
        <form class="appLoginForm" method="post" action="doSignIn" onsubmit='this.classList.add("loading")'>
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
        <div class="authMethods cmsChoices">
            <?php if ($authPasskey) { ?>
                <a class="authMethod cmsChoice ajax" href="?method=passkey">
                    <i class="cmsIcon cmsIcon-user-viewfinder"></i>
                    <span><b>Passkey</b><small>Face ID, Touch ID or a security key.</small></span>
                    <i class="cmsIcon cmsIcon-chevron-right"></i>
                </a>
            <?php } ?>
            <?php if ($authCodes) { ?>
                <a class="authMethod cmsChoice ajax" href="?method=code">
                    <i class="cmsIcon cmsIcon-input-numeric"></i>
                    <span><b>Scan or enter code</b><small>You have a sign-in code or QR code.</small></span>
                    <i class="cmsIcon cmsIcon-chevron-right"></i>
                </a>
                <a class="authMethod cmsChoice ajax" href="?method=device_code">
                    <i class="cmsIcon cmsIcon-qrcode"></i>
                    <span><b>Sign in with another device</b><small>Use another signed-in device to sign in here.</small></span>
                    <i class="cmsIcon cmsIcon-chevron-right"></i>
                </a>
            <?php } ?>
            <a class="authMethod cmsChoice ajax" href="?method=password">
                <i class="cmsIcon cmsIcon-input-password"></i>
                <span><b>Username &amp; password</b><small>Enter your username or email and password.</small></span>
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
