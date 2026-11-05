<?php
$fixedUser = $this->screen;
if (!$fixedUser) return;
$fixedMode = $this->scanning ? 'scan' : 'create';
$fixedClose = $this->parent->href;
$fixedRefresh = $this->parent->href . 'refreshDevices';
include dirname(__DIR__, 3) . '/user/fixedDialogs.tpl.php';
