<?php
/** Shared display/copy control. The existing data-copy handler owns clipboard behaviour. */
$copyValue = htmlspecialchars((string) $copyCode, ENT_QUOTES);
?>
<div class="qrCodeBar">
    <strong><?= $copyValue ?></strong>
    <button type="button" class="button transparent icononly" data-copy="<?= $copyValue ?>" aria-label="Copy code" title="Copy code"><i class="cmsIcon cmsIcon-copy" aria-hidden="true"></i></button>
</div>
