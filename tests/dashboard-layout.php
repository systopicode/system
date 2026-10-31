<?php
require __DIR__.'/bootstrap.php';
use Systopic\System\Dashboard\Layout;
$keys=['a','b','c','d','e','f'];
$legacy=['c','a','|','b','e','|','f','d'];
$data=Layout::read(['order'=>$legacy,'hidden'=>['e']]);
check('old three-column layouts retain exact column membership',Layout::columns($keys,$data['order'])===[['c','a'],['b','e'],['f','d']]);
check('old flat layouts keep historical column-major ordering',Layout::columns($keys,['c','a','b','e','f','d'])===[['c','a'],['b','e'],['f','d']]);
$two=['c','b','d','|','a','f','e'];
$saved=Layout::save($data,$two,['e'],2);
check('tablet sort retains desktop order and hidden widgets',$saved['order']===$legacy && $saved['hidden']===['e']);
$one=Layout::save($saved,['f','c','a','b','e','d'],['e'],1);
check('phone sort does not change saved tablet or desktop layouts',$one['layouts'][2]===$two && $one['order']===$legacy);
check('per-view orders survive JSON storage and reload',Layout::read(json_decode(json_encode($one),true))===$one);
check('bad legacy data safely falls back',Layout::read(null)===['order'=>[],'hidden'=>[],'layouts'=>[]]);
foreach([1,2,3] as $count){
 $columns=Layout::columns([...$keys,'new'],['a','a','|','b','unknown','|','c'],$count);
 $flat=array_merge(...$columns);sort($flat);$expected=[...$keys,'new'];sort($expected);
 check("$count columns contain every permitted widget once",count($columns)===$count&&$flat===$expected);
}
