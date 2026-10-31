<?php
require __DIR__.'/bootstrap.php';
define('STAGE','DEV');
class emailSmtp {
 public array $to=[],$cc=[],$bcc=[],$reply_to=[];public string $subject='',$body='';
 function addTo($address){$this->to[]=[$address,''];}
 function Subject($value){$this->subject=$value;}
 function Body($value){$this->body=$value;}
 function Send(){throw new RuntimeException('No real transport allowed in this test.');}
}
use Systopic\System\Mail\{TestDelivery,Delivery};
$result=TestDelivery::send(static function($mail){check('test uses routed recipient',$mail->to===[[Delivery::DEFAULT_RECIPIENT,'Test recipient']]);check('test has no CC/BCC',$mail->cc===[]&&$mail->bcc===[]);return true;});
check('SMTP acceptance reported',$result['ok']&&$result['testMode']&&$result['recipient']===Delivery::DEFAULT_RECIPIENT);
check('transport rejection reported',!TestDelivery::send(static fn()=>false)['ok']);
