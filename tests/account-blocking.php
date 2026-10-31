<?php
require __DIR__.'/bootstrap.php';
use Systopic\System\Auth\AccountAccess;
use Systopic\System\Tables\Users\Model as User;
$user=new User();$user->name='test';$user->isActive=true;$user->isSuperuser=true;$user->sessionKey=null;$user->activationKey='old-key';
check('legacy empty session remains valid',AccountAccess::sessionValid($user,''));
AccountAccess::suspend($user);$key=$user->sessionKey;
check('blocking is separate from activation',$user->isBlocked&&$user->isActive);
check('session generation rotated',strlen($key)===64&&!AccountAccess::sessionValid($user,''));
check('activation link invalidated',$user->activationKey===null);
$user->isActive=true;
check('reactivation does not lift suspension',!AccountAccess::sessionValid($user,$key));
$user->isBlocked=false;
check('unblocking does not revive an old session',!AccountAccess::sessionValid($user,''));
check('fresh sign-in may use current generation',AccountAccess::sessionValid($user,$key));
AccountAccess::suspend($user);$user->isBlocked=false;
check('repeated suspension invalidates previously issued sessions',!AccountAccess::sessionValid($user,$key));
check('rights are preserved',$user->isSuperuser);
echo "Account blocking checks passed.\n";
