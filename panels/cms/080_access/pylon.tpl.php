<?php

if ($this->childSelected instanceof \Systopic\System\Panels\Root\Cms\Access\Panelmap\Panel) {
    $this->withChildSelected('pylon');
} else {
    include '../child.pylon.tpl.php';
}
