<?php
require __DIR__ . '/bootstrap.php';
use Systopic\System\Auth\DeviceName;
$mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 Version/26.0 Safari/605.1.15';
$_SERVER['HTTP_USER_AGENT'] = $mac;
unset($_SERVER['HTTP_X_FLUXO_DEVICE']);
check('ordinary Mac remains Mac', DeviceName::from(DeviceName::requestAgent()) === 'Mac · Safari');
$_SERVER['HTTP_X_FLUXO_DEVICE'] = 'iPad';
$ipad = DeviceName::requestAgent();
check('desktop-mode iPad hint gives tablet name', DeviceName::from($ipad) === 'iPad · Safari');
check('desktop-mode iPad gives tablet icon', DeviceName::icon($ipad) === 'cmsIcon cmsIcon-tablet-screen');
check('mobile iPad UA works without hint', DeviceName::from($mac . ' Mobile/15E148') === 'iPad · Safari');
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0';
check('hint cannot turn Windows into iPad', DeviceName::from(DeviceName::requestAgent()) === 'Windows · Chrome');
