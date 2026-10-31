<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
class http { public static string $root='/project/'; public static function sessionStartOnce(): void {} }
use Systopic\System\Auth\Branding;
foreach ([['/project/about/',true],['/project/cms/pages/',false],['/project/cms',false],['/other/',false],[null,false]] as [$target,$expected]) {
    $_SESSION['systopic_return_to']=$target;
    check('frontend branding destination '.($target??'(none)'),Branding::frontend()===$expected);
}
