<?php
declare(strict_types=1);
namespace Systopic\System\Config {
    final class Config {
        public static array $mail = [];
        public static function current(): object {
            return (object) ['instance' => new class {
                public function get(string $key): array { return Config::$mail; }
            }];
        }
    }
}
namespace Systopic\System\Auth { final class Session { public static function known(): bool { return false; } } }
namespace {
    class fs {
        public static string $root;
        public static function fileExists(string $path): bool { return false; }
        public static function isDir(string $path): bool { return is_dir($path); }
        public static function fsMkdir(string $path, int $mode, bool $recursive): bool { return mkdir($path, $mode, $recursive); }
        public static function file_put_contents(string $path, string $data, int $flags): int|false { return file_put_contents($path, $data, $flags); }
    }
    $case = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
    define('STAGE', $case['stage']); define('DEBUG', $case['debug'] ?? false);
    define('NL', "\r\n");
    fs::$root = $case['root'] . '/';
    \Systopic\System\Config\Config::$mail = ['testMode'=>$case['mode'], 'testRecipient'=>$case['recipient']];
    require dirname(__DIR__) . '/src/SMTPMailer.php';
    require dirname(__DIR__) . '/lib/class/email/smtp/emailSmtp.php';
    $mail = ($case['direct'] ?? false) ? new SMTPMailer('127.0.0.1', $case['port'], $case['secure'] ?? 'none') : new emailSmtp('127.0.0.1', $case['port'], $case['secure'] ?? 'none');
    if (!empty($case['auth'])) $mail->Auth('fixture-user','fixture-secret');
    $mail->From('sender@example.test');
    $mail->to = [['real-to@example.test','To']];
    $mail->cc = [['real-cc@example.test','CC']];
    $mail->bcc = [['real-bcc@example.test','BCC']];
    $mail->reply_to = [['real-reply@example.test','Reply']];
    $mail->Subject('Fixture'); $mail->body = 'FAKE_SECRET_FIXTURE';
    if (!($case['direct'] ?? false)) $mail->sensitive();
    try { $ok = $mail->Send(); echo json_encode(['ok'=>$ok]); }
    catch (\Throwable $e) { echo json_encode(['error'=>$e->getMessage()]); }
}
