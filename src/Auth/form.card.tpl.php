<?php
/** Shared layout for reset, code verification and password entry. */
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<main class="authSignin">
    <div class="authPane">
        <h1><?= $e($authTitle) ?></h1>
        <p class="authLead"><?= $e($authLead) ?></p>
        <div class="authMessages"><?= \message::flush([], true) ?></div>
        <?= $authForm ?>
    </div>
    <p class="authFoot"><a class="button transparent authSwitch ajax" href="<?= $e(\http::$root . 'auth/?method=choose') ?>"><i class="cmsIcon cmsIcon-arrow-left"></i><span>Use another method</span></a></p>
</main>
