<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Systopic\System\Tables\Users\Operator;
foreach ([true,false] as $stage) {
    check('short new password rejected in every stage', Operator::passwordProblems('Valid-13','Valid-13',$stage)!==[]);
    check('15 uncommon characters accepted without composition rules', Operator::passwordProblems('qxmvbrtpskjwzfh','qxmvbrtpskjwzfh',$stage)===[]);
    check('passphrase accepted',Operator::passwordProblems('a long passphrase','a long passphrase',$stage)===[]);
    check('mismatch rejected',Operator::passwordProblems('a long passphrase','different',$stage)!==[]);
    check('Unicode characters counted as characters',Operator::passwordProblems('äöüabcdefghijklm','äöüabcdefghijklm',$stage)===[]);
}

check('known common long password rejected', Operator::passwordProblems('123456789123456789','123456789123456789',true)!==[]);
check('repetition rejected', Operator::passwordProblems(str_repeat('ä',15),str_repeat('ä',15),false)!==[]);
check('special characters accepted as data', Operator::passwordProblems("A phrase with '\";\\ symbols", "A phrase with '\";\\ symbols",true)===[]);
check('common substring does not reject a whole passphrase', Operator::passwordProblems('my unique password walks home','my unique password walks home',true)===[]);

check('common alphabet sequence rejected', \Systopic\System\Auth\PasswordBlocklist::contains('abcdefghijklmno'));
check('screening normalises ASCII case and surrounding whitespace', \Systopic\System\Auth\PasswordBlocklist::contains('  ABCDEFGHIJKLMNO  '));
$data = file_get_contents(dirname(__DIR__) . '/src/Auth/data/common-passwords.bin');
check('expanded dataset contains more than 100000 values', strlen($data) / 32 > 100000);
$previous = null;
foreach (str_split($data, 32) as $digest) {
    if ($previous !== null && strcmp($previous, $digest) >= 0) throw new \RuntimeException('Unsorted blocklist');
    $previous = $digest;
}
check('binary dataset is sorted and unique', true);
