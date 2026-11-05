<?php
/** Shared code result for the three account device widgets. No authentication logic. */
?>
<div class="remoteOffer">
    <div data-code-live>
        <p class="cmsBoxLead">Scan this on the new device, or enter the code.</p>
        <div class="remoteQr"><?= $offerSvg ?></div>
        <?php $copyCode = $boxOffer['code']; include dirname(__DIR__, 3) . '/src/Auth/codeCopy.tpl.php'; ?>
        <p class="remoteExpiry">Valid for 10 minutes.</p>
    </div>
    <form class="ajax cmsBoxActions" data-feedback="parameters" method="put" action="<?= $e($this->href . 'createDeviceCode') ?>">
        <input type="hidden" name="kind" value="<?= $e($kind) ?>">
        <input type="hidden" name="account" value="<?= (int) $boxOffer['user'] ?>">
        <button type="submit" class="button submit"><i class="cmsIcon cmsIcon-qrcode"></i>Create another code</button>
    </form>
</div>
