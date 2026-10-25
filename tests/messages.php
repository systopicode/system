<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
class debug { public static function getCaller(): string { return 'test'; } }
function DEBUG(): bool { return false; }
require dirname(__DIR__).'/lib/class/message/message.php';
$_SESSION=[];
check('ordinary empty flush stays empty',message::flush()==='');
$placeholder=message::flush([],true);
check('auth can retain one shared empty message container',str_contains($placeholder,'data-system-messages')&&str_contains($placeholder,"aria-live='polite'"));
message::confirm('Saved.');message::warning('Check this.');
$html=message::flush([],true);
check('server messages use one shared container',substr_count($html,'data-system-messages')===1&&str_contains($html,"class='confirm'")&&str_contains($html,"class='warning'"));
check('messages are consumed once',message::flush()==='');
echo "All shared message checks passed.\n";
