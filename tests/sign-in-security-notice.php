<?php
declare(strict_types=1);
namespace Systopic\Db { interface Observer {} class Rows {} class Scope {static function default():self{return new self;}} }
namespace Systopic\System\Tables\Users { class Model {public function __construct(public int $id,public string $email,public string $name,public string $fullname=''){} } }
namespace Systopic\System\Tables\SignInCodes { class Model {public ?int $approvedBy=null;public ?int $createdBy=null;} }
namespace Systopic\System\Queries\UserLookup { class UserLookup {static function byId($scope,int $id):?\Systopic\System\Tables\Users\Model {return $id===9?new \Systopic\System\Tables\Users\Model(9,'admin@example.test','<Admin>'):null;}} }
namespace Systopic\System\Auth {class DeviceName {static function from(string $s):string{return 'iPhone · Safari';} static function requestAgent():string{return '';}} }
namespace Systopic\System\Mail {class Letter {static function note($s){return $s;} static function render($title,$body){return $title.$body;}} }
namespace Systopic\System\Sys {class LocalTime {const DATETIME='Y-m-d H:i';} }
namespace {
require __DIR__.'/../src/Auth/SecurityNotice.php';
class fs {static string $projectRoot='/nonexistent/';static function toNative($s){return $s;}}
class emailSmtp {
 static array $sent=[];static bool $fail=false;private array $mail=[];
 function sensitive(){return $this;} function To($address,$name){$this->mail['to']=$address;return $this;}
 function Subject($s){$this->mail['subject']=$s;return $this;} function Body($s){$this->mail['body']=$s;return $this;}
 function send(){if(self::$fail)throw new RuntimeException('offline');self::$sent[]=$this->mail;return true;}
}
function check($label,$ok){if(!$ok)throw new RuntimeException($label);echo "ok: $label\n";}
use Systopic\System\Auth\SecurityNotice;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\SignInCodes\Model as Code;
$user=new User(42,'person@example.test','Person');$code=new Code;$code->createdBy=42;
SecurityNotice::deviceSignedIn($user,$code);
check('self sign-in notifies target once',count(emailSmtp::$sent)===1&&emailSmtp::$sent[0]['to']==='person@example.test'&&str_contains(emailSmtp::$sent[0]['body'],'Authorized by: You'));
emailSmtp::$sent=[];$code->createdBy=42;$code->approvedBy=9;
SecurityNotice::deviceSignedIn($user,$code);
check('approval actor takes precedence and is escaped',count(emailSmtp::$sent)===1&&str_contains(emailSmtp::$sent[0]['body'],'&lt;Admin&gt;'));
check('other actor receives no copy',array_column(emailSmtp::$sent,'to')===['person@example.test']);
check('notice identifies device and recovery action',str_contains(emailSmtp::$sent[0]['body'],'iPhone · Safari')&&str_contains(emailSmtp::$sent[0]['body'],'revoke the unfamiliar device'));
$code->approvedBy=77;emailSmtp::$sent=[];SecurityNotice::deviceSignedIn($user,$code);
check('deleted actor retains recorded id',str_contains(emailSmtp::$sent[0]['body'],'Account #77'));
$user->email='';emailSmtp::$sent=[];SecurityNotice::deviceSignedIn($user,$code);
check('account without address sends no mail',emailSmtp::$sent===[]);
$user->email='person@example.test';emailSmtp::$fail=true;SecurityNotice::deviceSignedIn($user,$code);check('mail failure leaves successful authentication intact',true);
foreach(['panels/auth/actions.php','src/Auth/DeviceCode.php'] as $path){$source=file_get_contents(__DIR__.'/../'.$path);check('successful sign-in hook: '.$path,str_contains($source,'SecurityNotice::deviceSignedIn($user, $code)'));}
}
