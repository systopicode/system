<?php
require __DIR__ . '/bootstrap.php';
use Systopic\System\Auth\Portrait;
$colors = new ReflectionProperty(Portrait::class, 'colors');
$colors->setValue(null, ['ink'=>'203c48','background'=>'eef2f4','paper'=>'ffbb00']);
$user=Portrait::src(0,'person@example.test');
$screen=Portrait::src(0,null,true);
check('both fallbacks are local SVGs',str_starts_with($user,'data:image/svg+xml,')&&str_starts_with($screen,'data:image/svg+xml,'));
check('user and screen use distinct central icons',$user!==$screen);
check('avatar never includes email address',!str_contains(rawurldecode($user),'person@example.test'));
check('duotone secondary layer retained',str_contains(rawurldecode($user),'opacity=".35"')&&str_contains(rawurldecode($screen),'opacity=".35"'));
