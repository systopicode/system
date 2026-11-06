<?php
/** Shared code result for the three account device widgets. No authentication logic. */
?>
<?php $offerLeft = max(0, (int) $boxOffer['expires'] - time()); ?>
<div class="remoteOffer" data-code-until="<?= (int) $boxOffer['expires'] ?>" data-code-left="<?= $offerLeft ?>" data-offer-renew>
    <div data-code-live>
        <p class="cmsBoxLead">Scan this QR code or enter this code on the device you want to sign in.</p>
        <div class="remoteQr"><?= $offerSvg ?></div>
        <?php $copyCode = $boxOffer['code']; include dirname(__DIR__, 3) . '/src/Auth/codeCopy.tpl.php'; ?>
        <p class="remoteExpiry" role="timer">New code in <span data-code-countdown><?= intdiv($offerLeft, 60) . ':' . sprintf('%02d', $offerLeft % 60) ?></span></p>
    </div>
    <form class="ajax cmsBoxActions" data-feedback="parameters" method="put" action="<?= $e(($dialogBase ?? $this->href) . 'createDeviceCode') ?>">
        <input type="hidden" name="kind" value="<?= $e($kind) ?>">
        <input type="hidden" name="account" value="<?= (int) $boxOffer['user'] ?>">
        <button type="submit" class="button submit" data-focus-id="device-again-<?= $e($kind) ?>"><i class="cmsIcon cmsIcon-qrcode"></i>Create another code</button>
    </form>
</div>
