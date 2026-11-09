<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Systopic\System\Settings\CentrifugoHub;
use Systopic\System\Settings\EnvironmentFile;
class fs {
    public static string $projectRoot;
    public static function toNative(string $path): string { return $path; }
}
$root = sys_get_temp_dir() . '/fluxo-environment-' . bin2hex(random_bytes(6)) . '/';
mkdir($root . 'config', 0700, true);
fs::$projectRoot = $root;
$file = $root . 'config/DEV.test.config.json';
$initial = ['relay' => ['api'=>'http://127.0.0.1:8001', 'secret'=>'test-secret', 'apiKey'=>'test-key', 'client'=>'/connection/'], 'untouched'=>42];
file_put_contents($file,json_encode($initial));
file_put_contents($root . 'config/DEV.other.config.json', json_encode(['db'=>['name'=>'other']]));
$local = $root . 'config/config.local.json';
$config = \Systopic\System\Config\Config::boot($root . 'config', [], rtrim($root, '/'), 'DEV.test');
$F = 'DEV.test.config.json';
$value = static fn(array $path, string $name = 'DEV.test.config.json') => \Systopic\System\Config\Tree::get(EnvironmentFile::read($name), ['packages', ...$path]);
try {
    check('a file in the old layout reads in the new one', $value(['relay','secret'])==='test-secret');
    check('stop stores only an environment switch', EnvironmentFile::enableRelay($F,false));
    $raw = json_decode(file_get_contents($file), true);
    check('the first write moves the file to the new layout', !isset($raw['relay']) && isset($raw['packages']['relay']));
    check('secrets and unrelated settings survive stop', $value(['relay','secret'])==='test-secret' && $value(['relay','apiKey'])==='test-key' && $raw['untouched']===42);
    check('start restores this environment', EnvironmentFile::enableRelay($F,true) && $value(['relay','enabled'])===true);
    check('mail test mode saves', EnvironmentFile::set($F,'system','mail.testMode','1') && $value(['system','mail','testMode'])===true);
    check('test recipient trims and saves', EnvironmentFile::set($F,'system','mail.testRecipient',' sink@example.test ') && $value(['system','mail','testRecipient'])==='sink@example.test');
    check('invalid recipient rejected without changing saved value', !EnvironmentFile::set($F,'system','mail.testRecipient','not-an-address') && $value(['system','mail','testRecipient'])==='sink@example.test');
    check('empty recipient permits system fallback', EnvironmentFile::set($F,'system','mail.testRecipient','') && $value(['system','mail','testRecipient'])===null);
    check('test switch can be disabled without touching other settings', EnvironmentFile::set($F,'system','mail.testMode','0') && $value(['system','mail','testMode'])===false && $value(['relay','secret'])==='test-secret');
    check('invalid port rejected', !EnvironmentFile::set($F,'system','mail.port','70000'));
    check('non-numeric port rejected', !EnvironmentFile::set($F,'system','mail.port','abc'));
    check('invalid boolean rejected', !EnvironmentFile::set($F,'system','mail.testMode','maybe'));
    check('invalid TLS mode rejected', !EnvironmentFile::set($F,'system','mail.secure','other'));
    check('path traversal rejected', EnvironmentFile::path('../DEV.test.config.json') === null);
    $snapshot = file_get_contents($file);
    check('unknown configuration fields rejected', !EnvironmentFile::set($F,'db','connections.main.unknown','value') && !EnvironmentFile::set($F,'system','unknown','value'));
    check('a bad connection name is rejected', !EnvironmentFile::set($F,'db','connections.../x.host','value'));
    check('invalid writes leave file unchanged', file_get_contents($file) === $snapshot);
    check('mail edit preserves database configuration', EnvironmentFile::set($F,'db','connections.main.name','fixture_database') && EnvironmentFile::set($F,'system','mail.testRecipient','sink@example.test') && $value(['db','connections','main','name'])==='fixture_database');
    check('a second connection works the same', EnvironmentFile::set($F,'db','connections.revolver.driver','pgsql') && $value(['db','connections','revolver','driver'])==='pgsql');
    file_put_contents($file, '{invalid json');
    check('broken JSON cannot be overwritten by a field save', !EnvironmentFile::set($F,'system','mail.testRecipient','sink@example.test') && file_get_contents($file) === '{invalid json');
    file_put_contents($file, $snapshot);

    // secrets
    check('a secret goes to config.local.json, which may not exist yet', !is_file($local) && EnvironmentFile::set($F,'db','connections.main.pass','s3cret') && $value(['db','connections','main','pass'],'config.local.json')==='s3cret');
    check('and not into the instance file', !str_contains(file_get_contents($file),'s3cret'));
    check('an empty field keeps it', EnvironmentFile::set($F,'db','connections.main.pass','') && $value(['db','connections','main','pass'],'config.local.json')==='s3cret');
    check('a secret of another machine cannot be set here', !EnvironmentFile::set('DEV.other.config.json','db','connections.main.pass','x') && !str_contains(file_get_contents($local),'"x"'));
    check('an instance file takes the values of an installation', EnvironmentFile::set('DEV.other.config.json','db','connections.main.host','db.example'));
    check('config.local.json takes what may be overridden', EnvironmentFile::set('config.local.json','db','connections.main.host','127.0.0.1'));
    check('but not what may not', !EnvironmentFile::set('config.local.json','system','mail.host','x'));
    check('the effective value of the running instance', EnvironmentFile::effective($F,'db')['connections']['main']['pass']==='s3cret');
    check('another instance does not get this machine\'s secret', !isset(EnvironmentFile::effective('DEV.other.config.json','db')['connections']['main']['pass']));

    // sign-in key
    check('the sign-in key goes to config.local.json', EnvironmentFile::createAuth($F) && strlen((string) $value(['system','auth','secret'],'config.local.json'))===64 && $value(['system','auth','secret'])===null);
    $key = EnvironmentFile::authSecret($F);
    check('a second call leaves it', EnvironmentFile::createAuth($F) && EnvironmentFile::authSecret($F)===$key);
    check('replacing makes a new one', EnvironmentFile::createAuth($F,true) && EnvironmentFile::authSecret($F)!==$key);
    check('removing takes it away', EnvironmentFile::removeAuth($F) && EnvironmentFile::authSecret($F)==='');

    // hub
    check('connecting splits the hub block', EnvironmentFile::connectRelay($F,['api'=>'http://127.0.0.1:9','apiKey'=>'hub-key','secret'=>'hub-secret-long-enough','client'=>'/connection/','plainTopics'=>true]));
    check('the keys stay on this machine', $value(['relay','secret'],'config.local.json')==='hub-secret-long-enough' && $value(['relay','apiKey'],'config.local.json')==='hub-key' && $value(['relay','api'])==='http://127.0.0.1:9' && $value(['relay','plainTopics'])===true);

    // saving reconciles the file
    file_put_contents($file, json_encode(['packages'=>['db'=>['connections'=>['main'=>['name'=>'n','pass'=>'in-the-file']]],'system'=>['gone'=>1]]]));
    file_put_contents($root . 'config/DEV.other.config.json', json_encode(['packages'=>['system'=>['gone'=>1]]]));
    check('a save writes the change and nothing else', EnvironmentFile::set($F,'system','versionDate','2026-01-01') && $value(['system','debug','debug'])===null && $value(['db','connections','main','host'])===null);
    check('what nobody defines stays until the click', $value(['system','gone'])===1);
    check('the click takes it out, in the own file', EnvironmentFile::dropOrphans($F) && $value(['system','gone'])===null);
    check('another instance keeps it', !EnvironmentFile::dropOrphans('DEV.other.config.json') && $value(['system','gone'],'DEV.other.config.json')===1);
    check('a secret is not moved by a save', $value(['db','connections','main','pass'])==='in-the-file');
    check('emptying a field lets the default count again', EnvironmentFile::set($F,'db','connections.main.host','db.test') && EnvironmentFile::set($F,'db','connections.main.host','') && $value(['db','connections','main','host'])===null);
    check('secrets of another machine are not moved here', !EnvironmentFile::moveSecrets('DEV.other.config.json'));
    check('the click moves them to local', EnvironmentFile::moveSecrets($F) && $value(['db','connections','main','pass'],'config.local.json')!==null && !str_contains(file_get_contents($file),'in-the-file'));
    check('what local had stays', $value(['db','connections','main','pass'],'config.local.json')==='s3cret');
    file_put_contents($local, json_encode(['packages'=>['system'=>['mail'=>['host'=>'in-local']]]]));
    check('a value in the wrong file moves to the instance', EnvironmentFile::moveToInstance('system','mail.host') && $value(['system','mail','host'])==='in-local' && $value(['system','mail','host'],'config.local.json')===null);

    // layers, pruning, blocks
    file_put_contents($root . 'config/config.json', json_encode(['packages'=>['system'=>['auth'=>['deviceDays'=>90]]]]));
    file_put_contents($file, json_encode(['packages'=>['system'=>['debug'=>['debug'=>true,'render'=>false],'sysPath'=>'vendor/systopic/system/','auth'=>['deviceDays'=>90],'mail'=>['port'=>587]],'relay'=>['api'=>'http://x','client'=>'/connection/']]]));
    $layers = EnvironmentFile::layers($F);
    check('the layers of the running instance', $layers['project']['system']['auth']['deviceDays']===90 && isset($layers['instance']['system']['debug']) && is_array($layers['local']));
    check('another instance has no local layer', EnvironmentFile::layers('DEV.other.config.json')['local']===null);
    check('config.json has only itself', EnvironmentFile::layers('config.json')['instance']===null);
    $pruned = EnvironmentFile::prune($F);
    check('prune takes what repeats the package, the stage and config.json', is_array($pruned) && in_array('system.debug.debug',$pruned,true) && in_array('system.sysPath',$pruned,true) && in_array('system.auth.deviceDays',$pruned,true) && in_array('relay.client',$pruned,true));
    check('and keeps what differs', $value(['system','debug','render'])===false && $value(['relay','api'])==='http://x');
    check('an optional block keeps one value, or its defaults would go with it', $value(['system','mail','port'])===587);
    check('prune is only for the file running here', EnvironmentFile::prune('DEV.other.config.json')===false);
    check('an ini value of its own', EnvironmentFile::set($F,'system','ini.session_x','5') && $value(['system','ini','session_x'])==='5');
    check('but not a reserved one', !EnvironmentFile::set($F,'system','ini.display_errors','1'));
    check('local overrides start with the value that counts', EnvironmentFile::overrideLocal($F,'system','debug.render') && $value(['system','debug','render'],'config.local.json')===false);
    check('a secret is no override', !EnvironmentFile::overrideLocal($F,'db','connections.main.pass'));
    check('a connection is added', EnvironmentFile::addEntry($F,'db','connections','revolver') && $value(['db','connections','revolver','name'])==='revolver');
    check('a name the definition refuses is not', !EnvironmentFile::addEntry($F,'db','connections','Bad-name') && !EnvironmentFile::addEntry($F,'db','connections','revolver'));
    check('a block without names is no list of entries', !EnvironmentFile::addEntry($F,'system','mail','x'));
    check('main cannot be removed', EnvironmentFile::requiredEntry('db',['connections','main']) && !EnvironmentFile::removeBlock($F,'db','connections.main'));
    check('another one can', EnvironmentFile::removeBlock($F,'db','connections.revolver') && $value(['db','connections','revolver'])===null);
    check('the fluxo account fills the mail block', \Systopic\System\Settings\Tools\MailTool::reset($F) && $value(['system','mail','host'])===\Systopic\System\Mail\Delivery::DEFAULT_HOST);
    foreach ([['system', ['auth']], ['system', ['mail']], ['relay', []]] as [$pkg, $at]) {
        $node = \Systopic\System\Config\Explain::definition($pkg);
        foreach ($at as $key) $node = $node['properties'][$key] ?? [];
        check("x-tool of $pkg" . ($at ? '.' . implode('.', $at) : '') . ' is a SettingsTool', is_subclass_of((string) ($node['x-tool'] ?? ''), \Systopic\System\Settings\SettingsTool::class));
    }
    check('an optional block goes', EnvironmentFile::removeBlock($F,'system','mail') && $value(['system','mail'])===null);
    check('a block that is not optional stays', !EnvironmentFile::removeBlock($F,'system','debug'));
    check('only LIVE is the default', !EnvironmentFile::setDefault($F,true) && EnvironmentFile::setDefault($F,false));
    file_put_contents($root . 'config/STAGING.config.json', json_encode(['matches'=>[]]));
    file_put_contents($root . 'config/LIVE.config.json', json_encode(['default'=>true]));
    check('a config no rule points at may go', EnvironmentFile::deletable('STAGING.config.json') && EnvironmentFile::deleteInstance('STAGING.config.json') && !is_file($root . 'config/STAGING.config.json'));
    check('not the LIVE default', !EnvironmentFile::deletable('LIVE.config.json'));
    check('not the one running here', !EnvironmentFile::deletable($F));
    file_put_contents($root . 'config/RC.config.json', json_encode(['matches'=>[['serverName'=>'rc.test']]]));
    check('not one with a rule', !EnvironmentFile::deletable('RC.config.json'));

    // rules
    check('a rule is added', EnvironmentFile::addMatch($F));
    check('a list stays a list', EnvironmentFile::setMatch($F,0,'serverName','a.test, b.test') && EnvironmentFile::read($F)['matches'][0]['serverName']===['a.test','b.test']);
    check('one value is a string', EnvironmentFile::setMatch($F,0,'serverName','a.test') && EnvironmentFile::read($F)['matches'][0]['serverName']==='a.test');
    check('a bad address in a list is refused', !EnvironmentFile::setMatch($F,0,'serverIp','10.0.0.1, nope'));
    $origins = CentrifugoHub::projectOrigins([['serverName'=>'dev.local','serverIp'=>'192.168.10.66','path'=>'/project/'],['serverName'=>'dev.local'],['serverName'=>'evil.test/path'],['serverName'=>'*.test'],['serverIp'=>'::1']], 'https');
    check('addresses deduplicate, include IP and exclude paths and wildcards', $origins===['https://dev.local','https://192.168.10.66','https://[::1]']);
    check('exact and configured wildcard origins match', CentrifugoHub::originAllowed('https://dev.local',['https://dev.local']) && CentrifugoHub::originAllowed('https://a.example.test',['https://*.example.test']));
    check('different protocol or lookalike host does not match', !CentrifugoHub::originAllowed('http://dev.local',['https://dev.local']) && !CentrifugoHub::originAllowed('https://dev.local.evil',['https://dev.local']));
    $hubPath = $root . 'hub.json';
    $registry = $root . 'origins.json';
    file_put_contents($hubPath, json_encode(['client'=>['allowed_origins'=>['https://shared.test'], 'token'=>['hmac_secret_key'=>'test']], 'http_api'=>['key'=>'test-key']]));
    $change = \Systopic\System\Settings\CentrifugoOrigins::change(...);
    check('project can add a missing origin', $change($hubPath,$registry,'one','https://new.test',true));
    check('another project can register the same permission', $change($hubPath,$registry,'two','https://new.test',true));
    check('first removal keeps another project’s origin', $change($hubPath,$registry,'one','https://new.test',false) && in_array('https://new.test',json_decode(file_get_contents($hubPath),true)['client']['allowed_origins'],true));
    check('last owner can remove a managed origin', $change($hubPath,$registry,'two','https://new.test',false) && !in_array('https://new.test',json_decode(file_get_contents($hubPath),true)['client']['allowed_origins'],true));
    check('existing shared permission is preserved', $change($hubPath,$registry,'one','https://shared.test',true) && $change($hubPath,$registry,'one','https://shared.test',false) && in_array('https://shared.test',json_decode(file_get_contents($hubPath),true)['client']['allowed_origins'],true));
    $hub = json_decode(file_get_contents($hubPath),true);
    check('origin management preserves secrets', $hub['http_api']['key']==='test-key' && $hub['client']['token']['hmac_secret_key']==='test');
    check('unowned removal rejected', !$change($hubPath,$registry,'other','https://shared.test',false));
    unlink($hubPath); unlink($registry); unlink($registry.'.lock');
} finally {
    array_map('unlink', glob($root . 'config/*') ?: []); rmdir($root.'config'); rmdir($root);
}
