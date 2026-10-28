<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$authMethod = 'signedIn';
include \fs::toNative((string) \fs::$sysRoot . 'src/Auth/login.card.tpl.php');
