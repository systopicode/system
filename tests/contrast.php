<?php
require __DIR__.'/bootstrap.php';
use Systopic\System\Settings\Contrast;
check('black-white contrast 21:1',abs(Contrast::ratio('#000000','#ffffff')-21)<.001);
check('identical colours 1:1',Contrast::ratio('123456','123456')===1.0);
check('pair order does not matter',abs(Contrast::ratio('123456','abcdef')-Contrast::ratio('abcdef','123456'))<.001);
rejects('invalid colour rejected',fn()=>Contrast::ratio('invalid','ffffff'));
