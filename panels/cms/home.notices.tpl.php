<?php
/** Persistent dashboard notices are independent of the user's widget layout. */
use Systopic\System\Auth\Session;
use Systopic\System\Maintenance\Overview;
use Systopic\System\Settings\LegalLinks;
if (!Session::isSuperuser()) return;
$notices = [];
if (Overview::needsAttention()) {
    $notices[] = [Overview::summary(), 'Review cleanup', 'broom', '/cms/tools/maintenance/cleanup/'];
}
if (LegalLinks::details('operator') === '') {
    $notices[] = ['Please check: operator details are not entered in System Privacy. Add them or verify that your linked imprint and privacy policy cover them.', 'Privacy settings', 'gear', '/cms/settings/pagesettings/'];
}
if ($notices === []) return;
$escape = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<aside class="dashNotices" aria-label="Needs attention">
<?php foreach ($notices as [$text, $label, $icon, $path]) { ?>
    <div class="dashNotice">
        <i class="fa-regular fa-triangle-exclamation" aria-hidden="true"></i>
        <p><?= $escape($text) ?></p>
        <a class="button transparent ajax" href="<?= $escape(rtrim((string) \http::$root, '/') . $path) ?>"><i class="fa-regular fa-<?= $escape($icon) ?>"></i><?= $escape($label) ?></a>
    </div>
<?php } ?>
</aside>
