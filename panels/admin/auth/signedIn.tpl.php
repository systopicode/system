<?php
/** @var \Systopic\System\Panels\Root\Admin\Auth\Panel $this */
$authMethod = 'signedIn';
include \fs::toNative((string) \fs::$sysRoot . 'src/Auth/login.card.tpl.php');
