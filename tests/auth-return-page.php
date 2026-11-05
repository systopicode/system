<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
class http {static string $root='/projects/fixture/public/';static function sessionStartOnce(){}}
use Systopic\System\Auth\Session;
$_SESSION=[];
Session::rememberReturn('/projects/fixture/public/admin/pages/?id=42');
check('valid deep link is preserved',Session::peekReturn()==='/projects/fixture/public/admin/pages/?id=42');
check('return link is consumed once',Session::takeReturn()==='/projects/fixture/public/admin/pages/?id=42'&&Session::takeReturn()===null);
foreach(['/projects/fixture/public/auth/logout','/projects/fixture/public/admin/auth/','/projects/fixture/public/admin/user/logout','/other-project/admin/'] as $url){
 Session::rememberReturn($url);check('authentication/control route is not a return destination: '.$url,Session::takeReturn()===null);
}
