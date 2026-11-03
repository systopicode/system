<?php
require __DIR__ . '/mail-theme-fixture.php';
\Systopic\System\Settings\ThemeAssets::$logos = true;
require dirname(__DIR__) . '/src/Mail/Letter.php';
echo \Systopic\System\Mail\Letter::render('Account activation', '<p>Hello Lars,</p><p>Welcome to fluxo.</p>' . \Systopic\System\Mail\Letter::accessWarning());
