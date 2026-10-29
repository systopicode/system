<?php
require __DIR__.'/bootstrap.php';
$entries=\Systopic\System\Sys\ThirdParty::entries();
foreach($entries as $entry){check('notice available: '.$entry['name'], $entry['texts']!==[] && min(array_map('strlen',$entry['texts']))>500);}
$dir=dirname(__DIR__).'/legal/third-party/';$manifest=json_decode(file_get_contents($dir.'provenance.json'),true);
foreach($manifest['files'] as $file=>$meta)check('notice provenance: '.$file,hash_file('sha256',$dir.$file)===$meta['sha256']);
