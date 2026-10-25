<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Systopic\System\Auth\SecurityNotice;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Passkeys\Model as Key;
class fs {
    public static string $projectRoot;
    public static string $siteRoot;
    public static string $sysRoot;
    public static function toNative(string $s): string { return $s; }
    public static function isFile(string $s): bool { return is_file($s); }
    public static function file_get_contents(string $s): string { return file_get_contents($s); }
}
class emailSmtp {
    public static array $sent = [];
    public static bool $fail = false;
    private array $mail = [];
    public function sensitive(): static { return $this; }
    public function To($address, $name): static { $this->mail['to']=$address; return $this; }
    public function Subject($text): static { $this->mail['subject']=$text; return $this; }
    public function Body($text): static { $this->mail['body']=$text; return $this; }
    public function send(): bool { if(self::$fail) throw new RuntimeException('SMTP unavailable'); self::$sent[]=$this->mail; return true; }
}
$work=sys_get_temp_dir().'/fluxo-notice-'.bin2hex(random_bytes(4)).'/';
mkdir($work.'overrides/img',0777,true);
file_put_contents($work.'overrides/img/logo.svg','<svg xmlns="http://www.w3.org/2000/svg"></svg>');
fs::$projectRoot=fs::$siteRoot=$work; fs::$sysRoot=dirname(__DIR__).'/';
$observer=new SecurityNotice();
$user=User::withId(42); $user->hydrate('email','old@example.test'); $user->hydrate('name','Person'); $user->hydrate('passwordHash','previous-secret-hash');
$user->passwordHash='replacement-secret-hash';
$observer->committed([$user],[]);
check('password change sends one notification',count(emailSmtp::$sent)===1);
check('shared template excludes hashes and escapes values',str_contains(emailSmtp::$sent[0]['body'],'Your password was changed')&&!str_contains(emailSmtp::$sent[0]['body'],'secret-hash'));
emailSmtp::$sent=[];
SecurityNotice::rehashing(fn()=>$observer->committed([$user],[]));
check('automatic hash upgrades send no notification',emailSmtp::$sent===[]);
$user->forgetChanges(); $user->email='new@example.test'; $observer->committed([$user],[]);
check('email changes notify previous and new addresses',array_column(emailSmtp::$sent,'to')===['old@example.test','new@example.test']);
$user->forgetChanges(); emailSmtp::$sent=[]; $user->email='NEW@example.test'; $observer->committed([$user],[]);
check('email casing alone sends no notification',emailSmtp::$sent===[]);
$user->forgetChanges();
$key=new Key(); $key->user=$user; $key->credentialId=new Systopic\Db\Type\Text('private-credential'); $key->name='<Laptop>';
$observer->committed([$key],[]);
check('added passkey sends escaped label without credential data',count(emailSmtp::$sent)===1&&str_contains(emailSmtp::$sent[0]['body'],'&lt;Laptop&gt;')&&!str_contains(emailSmtp::$sent[0]['body'],'private-credential'));
$key->forgetChanges(); emailSmtp::$sent=[]; $key->signCount=2; $key->lastUsedAt=new DateTimeImmutable(); $observer->committed([$key],[]);
check('normal passkey login sends no change notification',emailSmtp::$sent===[]);
$key->forgetChanges(); $key->name='New label'; $observer->committed([$key],[]);
check('renaming sends a notification',count(emailSmtp::$sent)===1);
emailSmtp::$sent=[]; $observer->committed([],[$key]);
check('removal sends a notification',count(emailSmtp::$sent)===1);
emailSmtp::$sent=[]; $observer->committed([],[$key,$user]);
check('account deletion does not send individual key notices',emailSmtp::$sent===[]);
emailSmtp::$fail=true; $observer->committed([],[$key]);
check('SMTP failure does not fail the committed change',true);
unlink($work.'overrides/img/logo.svg'); rmdir($work.'overrides/img'); rmdir($work.'overrides'); rmdir($work);
echo "All security notification checks passed.\n";
