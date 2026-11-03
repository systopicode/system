<?php
/** @var \Systopic\System\Panels\Root\Auth\Panel $this */
$authMethod = 'signedIn';
include \fs::toNative((string) \fs::$sysRoot . 'src/Auth/login.card.tpl.php');
