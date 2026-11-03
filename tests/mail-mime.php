<?php
declare(strict_types=1);
class fs { public static function fileExists($file): bool { return is_file($file); } }
require dirname(__DIR__) . '/src/SMTPMailer.php';
function check($label,$ok): void { if (!$ok) throw new RuntimeException($label); echo "ok: $label\n"; }
$_SERVER['HTTP_HOST'] = 'dev.local:8443';
$mail = new SMTPMailer('127.0.0.1', 1025, 'none');
$mail->From('sender@example.test', 'Büro "Fluxo"');
$mail->addTo('reader@example.test','Empfänger');
$mail->addBcc('hidden@example.test');
$mail->Subject(str_repeat('Überprüfung der Nachricht ',12));
$mail->Body('<style>.x{color:red}</style><h1>Hello</h1><p>Überprüfung</p><p><a href="https://example.test/reset?key=fixture&amp;x=1">Set password</a></p>');
$mail->File(['Prüfung.pdf',"%PDF-fixture\x00\xff"]);
$method = new ReflectionMethod(SMTPMailer::class,'doHeaders');
$message = $method->invoke($mail);
file_put_contents('/private/tmp/fluxo-mail-mime.eml',$message);
check('BCC omitted from headers', !str_contains($message,'hidden@example.test') && !str_contains($message,'Bcc:'));
check('ID uses sender domain', (bool) preg_match('/Message-ID: <[a-f0-9]+@example\.test>/', $message));
check('PHP version omitted', !str_contains($message,'X-Mailer:'));
check('plain alternative generated with link', str_contains($mail->text,'https://example.test/reset?key=fixture&x=1') && !str_contains($mail->text,'color:red'));
check('CRLF only', !preg_match('/(?<!\r)\n/', $message));
check('wire lines within RFC limit',max(array_map('strlen',explode("\r\n",$message)))<=998);
$mail->Subject("Bad\r\nBcc: victim@example.test");
try { $method->invoke($mail); throw new LogicException('injection accepted'); }
catch (RuntimeException $e) { check('header injection rejected',str_contains($e->getMessage(),'invalid header')); }
