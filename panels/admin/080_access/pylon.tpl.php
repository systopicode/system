<?php

$nested = $this->childSelected?->pylonBranch ?? false;
if (!$nested) {
    include '../child.pylon.tpl.php';
    return;
}
?>
<div class="pylon cms pylonColumns">
    <nav class="pylonPrimary" aria-label="Access"><?= $this->getNavi(1) ?></nav>
    <?php $this->withChildSelected('pylon'); ?>
</div>
