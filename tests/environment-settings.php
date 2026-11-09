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
$local = $root . 'config/config.local.json';
$project = $root . 'config/config.json';
file_put_contents($project, json_encode(['constants' => ['PROJECT_NAMESPACE' => 'Test']]));
file_put_contents($local, json_encode(['relay' => ['api'=>'http://127.0.0.1:8001', 'secret'=>'test-secret', 'apiKey'=>'test-key', 'client'=>'/connection/'], 'untouched'=>42, 'packages' => ['system' => ['instance' => 'DEV.test']]]));
$config = \Systopic\System\Config\Config::boot($root . 'config', [], rtrim($root, '/'));
$L = EnvironmentFile::LOCAL;
$value = static fn(array $path, string $name = 'config.local.json') => \Systopic\System\Config\Tree::get(EnvironmentFile::read($name), ['packages', ...$path]);
try {
    check('the installation writes config.local.json', EnvironmentFile::activeFile() === $L && EnvironmentFile::stage() === 'DEV');
    check('a file in the old layout reads in the new one', $value(['relay','secret'])==='test-secret');
    check('stop stores only an environment switch', EnvironmentFile::enableRelay(false));
    $raw = json_decode(file_get_contents($local), true);
    check('the first write moves the file to the new layout', !isset($raw['relay']) && isset($raw['packages']['relay']));
    check('secrets and unrelated settings survive stop', $value(['relay','secret'])==='test-secret' && $value(['relay','apiKey'])==='test-key' && $raw['untouched']===42);
    check('start restores this installation', EnvironmentFile::enableRelay(true) && $value(['relay','enabled'])===true);
    check('mail test mode saves', EnvironmentFile::set('system','mail.testMode','1') && $value(['system','mail','testMode'])===true);
    check('test recipient trims and saves', EnvironmentFile::set('system','mail.testRecipient',' sink@example.test ') && $value(['system','mail','testRecipient'])==='sink@example.test');
    check('invalid recipient rejected without changing saved value', !EnvironmentFile::set('system','mail.testRecipient','not-an-address') && $value(['system','mail','testRecipient'])==='sink@example.test');
    check('empty recipient permits system fallback', EnvironmentFile::set('system','mail.testRecipient','') && $value(['system','mail','testRecipient'])===null);
    check('test switch can be disabled without touching other settings', EnvironmentFile::set('system','mail.testMode','0') && $value(['system','mail','testMode'])===false && $value(['relay','secret'])==='test-secret');
    check('invalid port rejected', !EnvironmentFile::set('system','mail.port','70000'));
    check('non-numeric port rejected', !EnvironmentFile::set('system','mail.port','abc'));
    check('invalid boolean rejected', !EnvironmentFile::set('system','mail.testMode','maybe'));
    check('invalid TLS mode rejected', !EnvironmentFile::set('system','mail.secure','other'));
    check('only config.json and config.local.json are files here', EnvironmentFile::source('../config.local.json') === null && EnvironmentFile::source('DEV.test.config.json') === null && EnvironmentFile::path('config.json') === null);
    $snapshot = file_get_contents($local);
    check('unknown configuration fields rejected', !EnvironmentFile::set('db','connections.main.unknown','value') && !EnvironmentFile::set('system','unknown','value'));
    check('a bad connection name is rejected', !EnvironmentFile::set('db','connections.../x.host','value'));
    check('a bad installation name is rejected', !EnvironmentFile::set('system','instance','dev ojhome'));
    check('the name cannot be emptied', !EnvironmentFile::set('system','instance',''));
    check('invalid writes leave file unchanged', file_get_contents($local) === $snapshot);
    check('mail edit preserves database configuration', EnvironmentFile::set('db','connections.main.name','fixture_database') && EnvironmentFile::set('system','mail.testRecipient','sink@example.test') && $value(['db','connections','main','name'])==='fixture_database');
    check('a second connection works the same', EnvironmentFile::set('db','connections.revolver.driver','pgsql') && $value(['db','connections','revolver','driver'])==='pgsql');
    check('site addresses take several, separated by commas', EnvironmentFile::set('cms','siteAddress','rc.example.test, {name}.example.test') && $value(['cms','siteAddress'])===['rc.example.test','{name}.example.test']);
    check('one is a string', EnvironmentFile::set('cms','siteAddress','/projects(\.[a-z]+)?/{site}/') && $value(['cms','siteAddress'])==='/projects(\.[a-z]+)?/{site}/');
    file_put_contents($local, '{invalid json');
    check('broken JSON cannot be overwritten by a field save', !EnvironmentFile::set('system','mail.testRecipient','sink@example.test') && file_get_contents($local) === '{invalid json');
    file_put_contents($local, $snapshot);

    // values of the project
    check('config.json is never written', !EnvironmentFile::set('system','constants.PROJECT_NAMESPACE','X') && json_decode(file_get_contents($project), true)['constants']['PROJECT_NAMESPACE']==='Test');
    check('what the project allows is overridden here', EnvironmentFile::set('system','auth.deviceDays','30') && $value(['system','auth','deviceDays'])===30);
    check('an ini value of its own', EnvironmentFile::set('system','ini.session_x','5') && $value(['system','ini','session_x'])==='5');
    check('but not a reserved one', !EnvironmentFile::set('system','ini.display_errors','1'));

    // secrets
    check('a secret goes to config.local.json', EnvironmentFile::set('db','connections.main.pass','s3cret') && $value(['db','connections','main','pass'])==='s3cret');
    check('an empty field keeps it', EnvironmentFile::set('db','connections.main.pass','') && $value(['db','connections','main','pass'])==='s3cret');
    check('the effective value of the installation', EnvironmentFile::effective('db')['connections']['main']['pass']==='s3cret');

    // sign-in key
    check('the sign-in key goes to config.local.json', EnvironmentFile::createAuth() && strlen((string) $value(['system','auth','secret']))===64);
    $key = EnvironmentFile::authSecret();
    check('a second call leaves it', EnvironmentFile::createAuth() && EnvironmentFile::authSecret()===$key);
    check('replacing makes a new one', EnvironmentFile::createAuth(true) && EnvironmentFile::authSecret()!==$key);
    check('removing takes it away', EnvironmentFile::removeAuth() && EnvironmentFile::authSecret()==='');

    // hub
    check('connecting writes the hub block here', EnvironmentFile::connectRelay(['api'=>'http://127.0.0.1:9','apiKey'=>'hub-key','secret'=>'hub-secret-long-enough','client'=>'/connection/','plainTopics'=>true]));
    check('keys and the rest in one file', $value(['relay','secret'])==='hub-secret-long-enough' && $value(['relay','apiKey'])==='hub-key' && $value(['relay','api'])==='http://127.0.0.1:9' && $value(['relay','plainTopics'])===true);

    // saving reconciles the file
    file_put_contents($local, json_encode(['packages'=>['system'=>['instance'=>'DEV.test','gone'=>1],'db'=>['connections'=>['main'=>['name'=>'n','pass'=>'p']]]]]));
    file_put_contents($project, json_encode(['packages'=>['system'=>['gone'=>1,'auth'=>['deviceDays'=>90]]]]));
    check('a save writes the change and nothing else', EnvironmentFile::set('system','versionDate','2026-01-01') && $value(['system','debug','debug'])===null && $value(['db','connections','main','host'])===null);
    check('what nobody defines stays until the click', $value(['system','gone'])===1);
    check('the click takes it out of config.local.json', EnvironmentFile::dropOrphans() && $value(['system','gone'])===null);
    check('config.json keeps it', $value(['system','gone'],'config.json')===1);
    check('emptying a field lets the default count again', EnvironmentFile::set('db','connections.main.host','db.test') && EnvironmentFile::set('db','connections.main.host','') && $value(['db','connections','main','host'])===null);

    // layers, pruning, blocks
    file_put_contents($local, json_encode(['packages'=>['system'=>['instance'=>'DEV.test','debug'=>['debug'=>true,'render'=>false],'sysPath'=>'vendor/systopic/system/','auth'=>['deviceDays'=>90],'mail'=>['port'=>587]],'relay'=>['api'=>'http://x','client'=>'/connection/']]]));
    $layers = EnvironmentFile::layers();
    check('the layers of the installation', $layers['project']['system']['auth']['deviceDays']===90 && isset($layers['local']['system']['debug']));
    check('the project alone', EnvironmentFile::layers(true)['local']===null);
    $pruned = EnvironmentFile::prune();
    check('prune takes what repeats the package, the stage and config.json', is_array($pruned) && in_array('system.debug.debug',$pruned,true) && in_array('system.sysPath',$pruned,true) && in_array('system.auth.deviceDays',$pruned,true) && in_array('relay.client',$pruned,true));
    check('and keeps what differs', $value(['system','debug','render'])===false && $value(['relay','api'])==='http://x' && $value(['system','instance'])==='DEV.test');
    check('an optional block keeps one value, or its defaults would go with it', $value(['system','mail','port'])===587);
    check('a connection is added', EnvironmentFile::addEntry('db','connections','revolver') && $value(['db','connections','revolver','name'])==='revolver');
    check('a name the definition refuses is not', !EnvironmentFile::addEntry('db','connections','Bad-name') && !EnvironmentFile::addEntry('db','connections','revolver'));
    check('a block without names is no list of entries', !EnvironmentFile::addEntry('system','mail','x'));
    check('main cannot be removed', EnvironmentFile::requiredEntry('db',['connections','main']) && !EnvironmentFile::removeBlock('db','connections.main'));
    check('another one can', EnvironmentFile::removeBlock('db','connections.revolver') && $value(['db','connections','revolver'])===null);
    check('the fluxo account fills the mail block', \Systopic\System\Settings\Tools\MailTool::reset() && $value(['system','mail','host'])===\Systopic\System\Mail\Delivery::DEFAULT_HOST);
    foreach ([['system', ['auth']], ['system', ['mail']], ['relay', []]] as [$pkg, $at]) {
        $node = \Systopic\System\Config\Explain::definition($pkg);
        foreach ($at as $key) $node = $node['properties'][$key] ?? [];
        check("x-tool of $pkg" . ($at ? '.' . implode('.', $at) : '') . ' is a SettingsTool', is_subclass_of((string) ($node['x-tool'] ?? ''), \Systopic\System\Settings\SettingsTool::class));
    }
    check('an optional block goes', EnvironmentFile::removeBlock('system','mail') && $value(['system','mail'])===null);
    check('a block that is not optional stays', !EnvironmentFile::removeBlock('system','debug'));

    // the hub
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
