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
try {
    check('stop stores only an environment switch', EnvironmentFile::enableRelay('DEV.test.config.json',false));
    $data = EnvironmentFile::read('DEV.test.config.json');
    check('secrets and unrelated settings survive stop', $data['relay']['secret']==='test-secret' && $data['relay']['apiKey']==='test-key' && $data['untouched']===42 && $data['relay']['enabled']===false);
    check('start restores this environment', EnvironmentFile::enableRelay('DEV.test.config.json',true) && EnvironmentFile::read('DEV.test.config.json')['relay']['enabled']===true);
    check('mail test mode saves', EnvironmentFile::set('DEV.test.config.json','mail','testMode','1') && EnvironmentFile::read('DEV.test.config.json')['mail']['testMode'] === true);
    check('test recipient trims and saves', EnvironmentFile::set('DEV.test.config.json','mail','testRecipient',' sink@example.test ') && EnvironmentFile::read('DEV.test.config.json')['mail']['testRecipient'] === 'sink@example.test');
    check('invalid recipient rejected without changing saved value', !EnvironmentFile::set('DEV.test.config.json','mail','testRecipient','invalid') && EnvironmentFile::read('DEV.test.config.json')['mail']['testRecipient'] === 'sink@example.test');
    check('empty recipient permits system fallback', EnvironmentFile::set('DEV.test.config.json','mail','testRecipient',''));
    check('test switch can be disabled without touching other settings', EnvironmentFile::set('DEV.test.config.json','mail','testMode','0') && EnvironmentFile::read('DEV.test.config.json')['mail']['testMode'] === false && EnvironmentFile::read('DEV.test.config.json')['untouched'] === 42);
    check('invalid port rejected', !EnvironmentFile::set('DEV.test.config.json','mail','port','70000'));
    check('non-numeric port rejected', !EnvironmentFile::set('DEV.test.config.json','mail','port','abc'));
    check('invalid boolean rejected', !EnvironmentFile::set('DEV.test.config.json','mail','testMode','maybe'));
    check('invalid TLS mode rejected', !EnvironmentFile::set('DEV.test.config.json','mail','secure','other'));
    check('path traversal rejected', EnvironmentFile::path('../DEV.test.config.json') === null);
    $snapshot = file_get_contents($file);
    check('unknown configuration fields rejected', !EnvironmentFile::set('DEV.test.config.json','db','unknown','value'));
    check('invalid writes leave file unchanged', file_get_contents($file) === $snapshot);
    check('mail edit preserves database configuration', EnvironmentFile::set('DEV.test.config.json','db','name','fixture_database') && EnvironmentFile::set('DEV.test.config.json','mail','testRecipient','sink@example.test') && EnvironmentFile::read('DEV.test.config.json')['db']['name'] === 'fixture_database');
    file_put_contents($file, '{invalid json');
    check('broken JSON cannot be overwritten by a field save', !EnvironmentFile::set('DEV.test.config.json','mail','testRecipient','sink@example.test') && file_get_contents($file) === '{invalid json');
    file_put_contents($file, $snapshot);
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
    unlink($file); rmdir($root.'config'); rmdir($root);
}
