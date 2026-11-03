<?php
require __DIR__.'/bootstrap.php';
use Systopic\System\Tables\Medias\Files;
$neutral=['b'=>100,'c'=>100,'s'=>100,'r'=>0,'f'=>0];
$turned=Files::composeTune([],array_replace($neutral,['r'=>90]));
$colour=Files::composeTune($turned,array_replace($neutral,['b'=>120,'s'=>80]));
check('colour correction keeps saved rotation',$colour['r']===90 && $colour['b']===120 && $colour['s']===80);
$next=Files::composeTune($colour,array_replace($neutral,['r'=>90]));
check('rotation keeps saved colour correction',$next['r']===180 && $next['b']===120 && $next['s']===80);
$flipped=Files::composeTune($turned,array_replace($neutral,['f'=>1]));
check('horizontal reflection composes rotated axes',$flipped['r']===270 && $flipped['f']===1);
$restored=Files::composeTune($flipped,array_replace($neutral,['f'=>1]));
check('reflecting twice returns to saved rotation',$restored['r']===90 && $restored['f']===0);
