<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Systopic\Db\Connection;
use Systopic\Db\Scope;
use Systopic\Db\Type\Text;
use Systopic\System\Auth\PasskeyUpgrade;
use Systopic\System\Tables\Passkeys\Model as Key;
use Systopic\System\Tables\Users\Model as User;
$db=Connection::create(getenv('FLUXO_TEST_DSN'),getenv('FLUXO_TEST_USER')?:'root',getenv('FLUXO_TEST_PASSWORD')?:'');
check('dedicated disposable upgrade database',(string)$db->value('SELECT DATABASE()')==='fluxo_test_passkey_upgrade');
$db->run('DROP TABLE IF EXISTS usm_passkeys');
try {
$db->run("CREATE TABLE usm_passkeys (passkey_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, passkey_user_id INT UNSIGNED NOT NULL, passkey_credential_id TEXT NOT NULL, passkey_credential_sha CHAR(64) NOT NULL, passkey_public_key MEDIUMTEXT NOT NULL, passkey_sign_count INT UNSIGNED NOT NULL DEFAULT 0, passkey_name VARCHAR(64) NULL, passkey_record MEDIUMTEXT NOT NULL, passkey_created_at DATETIME NOT NULL, passkey_last_used_at DATETIME NULL, passkey_credential_hash VARCHAR(64) NOT NULL)");
$db->run("INSERT INTO usm_passkeys (passkey_user_id,passkey_credential_id,passkey_credential_sha,passkey_public_key,passkey_record,passkey_created_at,passkey_credential_hash) VALUES (2,'legacy','legacy-digest','public','legacy-record',NOW(),'legacy-digest')");
PasskeyUpgrade::run($db);PasskeyUpgrade::run($db);
check('legacy record preserved by repeatable upgrade',$db->value('SELECT passkey_record FROM usm_passkeys WHERE passkey_id=1')==='legacy-record');
check('legacy credentials are not silently assigned to a project',$db->value('SELECT passkey_project FROM usm_passkeys WHERE passkey_id=1')==='');
$scope=new Scope($db);
$key=new Key();$key->user=User::withId(2);$key->project=str_repeat('a',64);$key->credentialHash=str_repeat('b',64);$key->credentialId=new Text('new-id');$key->publicKey=new Text('public-key');$key->rpId='dev.local';$key->userHandle=str_repeat('c',64);$key->name=str_repeat('x',100);$key->signCount=0;$key->createdAt=new DateTimeImmutable();
$scope->save($key);
check('new ORM credential saves in upgraded legacy table',(int)$key->id===2);
check('digest reuses the existing column',$db->value('SELECT passkey_credential_sha FROM usm_passkeys WHERE passkey_id=2')===str_repeat('b',64));
echo "All legacy passkey upgrade checks passed.\n";
} finally {$db->run('DROP TABLE usm_passkeys');}
