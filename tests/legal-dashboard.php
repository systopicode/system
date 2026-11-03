<?php
require __DIR__.'/bootstrap.php';
class fs {public static string $projectRoot='/private/tmp/fluxo-unconfigured-legal-dashboard/';static function toNative(string $s):string{return $s;}}
class http {public static string $root='/project/';}
$render=static function(){ob_start();include dirname(__DIR__).'/panels/admin/090_tools/dashboard.cleanup.tpl.php';return ob_get_clean();};
check('privacy card is discoverable under its existing widget name',in_array('dashboard.cleanup.tpl.php',array_map('basename',glob(dirname(__DIR__).'/panels/admin/090_tools/dashboard*.tpl.php'))));
$empty=$render();
check('missing local operator information shows actionable hint',str_contains($empty,'Operator details not entered here'));
check('no DPO appointment is assumed',!str_contains($empty,'officer'));
file_put_contents('/private/tmp/fluxo-privacy-card.html',$empty);
define('CMS_OPERATOR_DETAILS',"Example & Co\ncontact@example.org");
$filled=$render();
check('entered operator details replace hint',!str_contains($filled,'not entered here') && str_contains($filled,'Operator details are included'));
