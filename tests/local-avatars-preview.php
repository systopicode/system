<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require getenv('FLUXO_DICEBEAR_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
use Systopic\System\Auth\LocalAvatar;
use Systopic\System\Auth\Portrait;
$colors=['ink'=>'203c48','background'=>'eef2f4','paper'=>'ffbb00'];
(new ReflectionProperty(Portrait::class,'colors'))->setValue(null,$colors);
$items=[];
foreach([1,2,3,4]as$i)$items[]=['label'=>'Person '.$i,'svg'=>LocalAvatar::render(LocalAvatar::seed($i,'preview-'.$i.'@example.test',false),false,$colors)];
foreach([1,2]as$i)$items[]=['label'=>'Screen '.$i,'svg'=>LocalAvatar::render(LocalAvatar::seed($i,null,true),true,$colors)];
foreach([false,true]as$screen)$items[]=['label'=>$screen?'Screen fallback':'Person fallback','svg'=>rawurldecode(substr(Portrait::src(0,null,$screen),strlen('data:image/svg+xml,')))];
echo json_encode($items,JSON_THROW_ON_ERROR);
