<?php
use Systopic\System\Maintenance\Overview;
use Systopic\System\Settings\LegalLinks;
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$root = rtrim((string) \http::$root, '/');
?>
<header><h2><i class="cmsIcon cmsIcon-shield-check"></i>Privacy &amp; cleanup</h2></header>
<main class="cleanupCard">
    <p><?= $e(Overview::summary()) ?></p>
    <p class="tagsHint">Only expired logs and temporary records. Accounts, content and backup files stay.</p>
    <div class="cleanupCardLegal">
        <strong>Privacy information</strong>
        <?php if (LegalLinks::details('operator') === '') { ?>
        <p class="cleanupOperatorHint"><i class="cmsIcon cmsIcon-triangle-exclamation"></i>Operator details not entered here. Add them in Settings or verify they are covered by your linked imprint and privacy policy.</p>
        <?php } else { ?><p class="tagsHint">Operator details are included in System Privacy.</p><?php } ?>
        <div class="cleanupLegalLinks">
        <?php foreach (['imprint' => 'Imprint', 'privacy' => 'Privacy policy'] as $kind => $label) { ?>
        <a class="button transparent cmsExternalLink" href="<?= $e(LegalLinks::url($kind)) ?>" target="_blank" rel="noopener noreferrer"><?= $e($label) ?><i class="cmsIcon cmsIcon-arrow-up-right-from-square" aria-hidden="true"></i></a>
        <?php } ?>
        </div>
    </div>
</main>
<footer><a class="button ajax" href="<?= $e($root . '/cms/settings/pagesettings/') ?>"><i class="cmsIcon cmsIcon-gear"></i>Privacy settings</a><a class="button ajax" href="<?= $e($root . '/cms/tools/cleanup/automatic/') ?>"><i class="cmsIcon cmsIcon-broom"></i>Open cleanup</a></footer>
