<?php
require __DIR__ . '/bootstrap.php';
use Systopic\System\Ui\Icons;
foreach (Icons::registry()['outline'] as $key => $asset) {
    check('registered key '.$key, Icons::classes($key) === 'cmsIcon cmsIcon-'.$key);
    check('local asset '.$asset, is_file(dirname(__DIR__).'/public/icons/'.(str_ends_with($asset, '-line-duotone') ? 'solar/' : 'tabler/').$asset.'.svg'));
}
check('native navigation classes resolve', Icons::classes('cmsIcon cmsIcon-duotone cmsIcon-gear') === 'cmsIcon cmsIcon-gear cmsIcon-duotone');
check('legacy classes are unsupported', Icons::classes('fa-duotone fa-gear') === 'cmsIcon cmsIcon-circle-question');
check('markup cannot escape the registry', !str_contains(Icons::html('check" onclick="alert(1)'), 'onclick'));
check('path traversal gets a bounded fallback', Icons::classes('../../secret') === 'cmsIcon cmsIcon-circle-question');
check('unsafe SVG input gets a bounded fallback', Icons::classes('<svg/onload=alert(1)>') === 'cmsIcon cmsIcon-circle-question');
$manifest=json_decode(file_get_contents(dirname(__DIR__).'/docs/icon-migration/assets.json'),true);
foreach($manifest['assets'] as $asset) check('asset integrity '.$asset['path'], hash_file('sha256',dirname(__DIR__).'/'.$asset['path']) === $asset['sha256']);
foreach(['public/css/11_fontawesome.min.css','public/fonts/fontawesome/fa-regular-400.woff2','public/fonts/fontawesome/fa-duotone-regular-400.woff2'] as $old) check('removed provider asset '.$old,!file_exists(dirname(__DIR__).'/'.$old));
$art=file_get_contents(dirname(__DIR__).'/src/Auth/passkey-icons.tpl.php');
check('licensed passkey artwork',str_contains($art,'Phosphor Icons 2.1.1') && !str_contains($art,'Font Awesome') && substr_count($art,'viewBox="0 0 256 256"')===3);

$notices=\Systopic\System\Sys\ThirdParty::entries();
$names=array_column($notices,'name');
check('unique third-party credit entries',count($names)===count(array_unique($names)));

foreach (['image-slash'=>'photo-off','video-slash'=>'movie-off','file-slash'=>'file-off','gif'=>'gif','video'=>'movie'] as $key=>$asset) check('dynamic media meaning '.$key, Icons::registry()['outline'][$key]===$asset);

check('blocked user is distinct from superuser', Icons::registry()['outline']['user-lock']!==Icons::registry()['outline']['user-shield']);
check('role assignment is distinct from edit user', Icons::registry()['outline']['user-tag']!==Icons::registry()['outline']['pen-to-square']);
foreach (\Systopic\System\Media\Presentation::ICONS as $ext=>$key) check('actual extension producer '.$ext, isset(Icons::registry()['outline'][$key]));
foreach (['iPhone Mobile','iPad Tablet','Macintosh'] as $agent) foreach ([\Systopic\System\Auth\DeviceName::icon($agent),\Systopic\System\Auth\DeviceName::slash($agent)] as $value) check('actual device producer '.$agent, Icons::classes($value)===$value);

check('cleanup uses shared Tabler eraser', Icons::registry()['outline']['broom'] === 'eraser');
check('Solar broom removed', !is_file(dirname(__DIR__).'/public/icons/solar/broom-line-duotone.svg'));
check('Solar info retained', Icons::registry()['outline']['circle-info'] === 'info-circle-line-duotone');
