<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Systopic\System\Tables\SignInCodes\Operator;
use Systopic\System\Tables\Users\Model as User;
$now = new DateTimeImmutable('2026-11-06T12:00:00Z');
foreach ([Operator::LIFETIME, Operator::DEVICE_CODE_LIFETIME] as $lifetime) {
    $code = Operator::created(User::withId(42), 42, str_repeat('a',64), null, $lifetime, $now);
    check('QR code lifetime is two minutes', $code->expiresAt->getTimestamp() - $now->getTimestamp() === 120);
    check('QR is usable before deadline', Operator::usable($code, $now->modify('+119 seconds')));
    check('QR expires after two minutes', !Operator::usable($code, $now->modify('+121 seconds')));
}
check('emailed invitation keeps separate lifetime', Operator::INVITATION_LIFETIME === 86400);
