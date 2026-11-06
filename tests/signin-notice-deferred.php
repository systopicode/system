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
function fastcgi_finish_request(): void { $GLOBALS['finished'] = true; }
$user = new User(42, 'person@example.test', 'Person');
$code = new Code; $code->createdBy = 42;
SecurityNotice::deviceSignedIn($user, $code);
check('SMTP does not run before the login response', emailSmtp::$sent === []);
register_shutdown_function(static function (): void {
    check('login response is finished before the notice is delivered', ($GLOBALS['finished'] ?? false) && count(emailSmtp::$sent) === 1);
});
}
