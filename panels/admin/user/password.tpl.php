<form class="widget" method="post" action="<?= $e($this->href . 'changePassword?section=security') ?>" data-password-check data-password-strict="<?= STAGE === 'LIVE' ? 'true' : 'false' ?>">
    <header><h2><i class="cmsIcon cmsIcon-input-password"></i>Password</h2></header>
    <main class="fieldGrid">
        <?= \message::flush('password') ?>
        <label class="field"><span>New password</span><input type="password" name="password" autocomplete="new-password" required></label>
        <label class="field"><span>Confirm new password</span><input type="password" name="password_repeat" autocomplete="new-password" required></label>
    </main>
    <footer><button class="button submit" type="submit" disabled><i class="cmsIcon cmsIcon-check"></i>Change password</button></footer>
</form>
