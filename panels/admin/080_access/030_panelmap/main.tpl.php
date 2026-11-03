<?php

$details = \http::get('panel', NULL) !== NULL || \http::get('detail') === '1';
?>
<div class="pylonSheetLayout" data-pylon-view="<?= $details ? 'detail' : 'tree' ?>">
    <?php
    $pylonInSheet = true;
    include __DIR__ . '/pylon.tpl.php';
    ?>
    <section class="cms pylonSheetDetail">
        <a class="button ajax pylonSheetBack" data-cms-inner-back href="./?tree=1"><i class="cmsIcon cmsIcon-arrow-left" aria-hidden="true"></i>Back to Panel Map</a>
        <?= message::flush() ?>
        <?php $this->withChildSelected(); ?>
    </section>
</div>
