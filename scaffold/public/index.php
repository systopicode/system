<?php

// The front controller is vendor/systopic/system/index.php.
$front = dirname(__DIR__) . '/vendor/systopic/system/index.php';
is_file($front) || exit('Please run "composer install" in ' . dirname(__DIR__));
require $front;
