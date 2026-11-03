<?php

declare(strict_types=1);
namespace Systopic\System\Maintenance;

final class Tasks
{
    public static function register(): void
    {
        foreach (['email' => 'Email delivery log', 'php' => 'PHP error log', 'backup' => 'Backup log'] as $kind => $label) {
            Runner::register($kind . '-log', $label, 'Technical entries older than 90 days. Business archives and backup files are kept separately. Undated legacy entries require administrator review.', static fn(bool $preview, int $limit, float $deadline) => RetainedLogs::cleanup($kind, $preview, $limit, $deadline));
        }
        Runner::register('login-log', 'Login and security log', 'Entries older than ' . LogRetention::LOGIN_DAYS . ' days, using the fixed system retention rule. Superusers can clear entries earlier.', static fn(bool $preview, int $limit, float $deadline) => LogRetention::cleanupLogin($preview, $limit, $deadline));
        Runner::register('login-codes', 'One-time sign-in codes', 'Used or expired codes are removed. Codes that are still valid are kept.', static function (bool $preview, int $limit): array {
            $scope = \Systopic\Db\Scope::default();
            $table = \Systopic\Db\Schema\Schema::tableNameOf(\Systopic\System\Tables\LoginCodes\Model::class);
            $exists = $scope->connection->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
            if (!(int) $exists) return ['count' => 0, 'more' => false];
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $where = 'logincode_used_at IS NOT NULL OR logincode_expires_at < ?';
            if ($preview) {
                $count = (int) $scope->connection->value("SELECT COUNT(*) FROM (SELECT 1 FROM $table WHERE $where LIMIT " . ($limit + 1) . ') AS candidates', [$now]);
            } else {
                $count = $scope->connection->run("DELETE FROM $table WHERE $where LIMIT $limit", [$now])->rowCount();
                if ($count) $scope->results->invalidate([$table]);
            }
            return ['count' => min($count, $limit), 'more' => $count >= $limit];
        });
        Runner::register('session-markers', 'Old session records', 'Temporary sign-in name records older than 90 days. Accounts, current sessions and login logs are kept.', static function (bool $preview, int $limit, float $deadline, int $cursor = 0): array {
            $dir = dirname(\Systopic\System\Auth\LoginLog::file()) . '/sessions';
            $count = 0; $visited = 0; $position = 0; $more = false;
            if (!is_dir($dir)) return compact('count', 'more');
            foreach (new \DirectoryIterator($dir) as $file) {
                if ($file->isDot()) continue;
                if ($position++ < $cursor) continue;
                if (++$visited > 500 || microtime(true) > $deadline || $count >= $limit) { $more = true; $position--; break; }
                if ($file->isLink() || !$file->isFile() || !preg_match('/^[a-f0-9]{64}$/D', $file->getFilename()) || $file->getMTime() >= time() - \Systopic\System\Auth\LoginLog::KEEP_DAYS * 86400) continue;
                if (!$preview && !unlink($file->getPathname())) throw new \RuntimeException('Session marker could not be removed.');
                $count++;
            }
            $cursor = $more ? max(0, $position - ($preview ? 0 : $count)) : 0;
            return compact('count', 'more', 'cursor');
        });
    }
}
