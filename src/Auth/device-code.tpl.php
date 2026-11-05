<?php
/** Shared QR, countdown and code/copy field, also returned for partial renewal. */
?>
                <?php $authLeft = max(0, (int) ($authPair['expires'] ?? 0) - time()); ?>
                <div class="authQrWrap" data-device-code data-code-until="<?= (int) ($authPair['expires'] ?? 0) ?>" data-code-left="<?= $authLeft ?>" data-code-renew>
                    <div data-code-live>
                        <div class="authQr"><?= $qr ?></div>
                        <?php $copyCode = $authPair['code'] ?? ''; include __DIR__ . '/codeCopy.tpl.php'; ?>
                        <p class="authLead authCountdown" role="timer">New code in <span data-code-countdown><?= intdiv($authLeft, 60) . ':' . sprintf('%02d', $authLeft % 60) ?></span></p>
                        <p class="authLead">Scan or enter code from your device that is signed in.</p>
                    </div>
                </div>
