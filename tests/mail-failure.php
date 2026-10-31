<?php
require __DIR__.'/bootstrap.php';
use Systopic\System\Mail\Failure;
foreach (['SMTP: expected 235, got 535 authentication failed'=>'authentication','SMTP: TLS handshake failed'=>'encrypted','SMTP: no connection (refused)'=>'reached','SMTP: expected 250, got 550 recipient rejected'=>'address'] as $error=>$hint) check('diagnoses '.$hint,str_contains(Failure::hint($error),$hint));
$secret='fixture-secret';$detail=Failure::detail("535 $secret ".base64_encode($secret)."\r\n failure",['password'=>$secret]);
check('removes raw and encoded credentials',!str_contains($detail,$secret)&&!str_contains($detail,base64_encode($secret))&&!str_contains($detail,"\n"));
