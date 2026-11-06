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
