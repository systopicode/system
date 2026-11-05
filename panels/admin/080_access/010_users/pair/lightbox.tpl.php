<?php
$fixedUser = $this->user;
if (!$fixedUser) return;
$fixedMode = \http::get('way') === 'scan' ? 'scan' : 'create';
$fixedClose = $this->parent->href;
$fixedRefresh = $this->parent->href . 'refreshDevices';
include dirname(__DIR__, 3) . '/user/fixedDialogs.tpl.php';
