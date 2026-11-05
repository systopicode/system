<?php

declare(strict_types=1);
namespace Systopic\System\Backup;

/** Backup execution records retain the actor independently of later account changes. */
final class LogEntry
{
    public static function format(string $event, int $userId, string $user, float $elapsed, float $memory, ?\DateTimeImmutable $date = null): string
    {
        $actor = json_encode(['id' => $userId, 'name' => $user], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        $event = str_replace(["\r", "\n", "\t"], ' ', $event);
        return '[' . ($date ?? new \DateTimeImmutable())->format('c') . '] ' . $event
            . ' - ' . $elapsed . 's ' . $memory . "MB\tuser=" . $actor . "\n";
    }

    /** Accept dated and undated legacy records without inventing an actor. */
    public static function parse(string $line): array
    {
        $line = trim($line);
        $row = ['event' => $line, 'date' => '', 'elapsed' => '', 'memory' => '', 'user' => '', 'userId' => 0];
        if (preg_match('/^(.*)\tuser=(\{.*\})$/', $line, $match)) {
            $actor = json_decode($match[2], true);
            if (is_array($actor) && is_int($actor['id'] ?? null) && is_string($actor['name'] ?? null)) {
                $line = $match[1];
                $row['user'] = $actor['name'];
                $row['userId'] = $actor['id'];
                $row['event'] = $line;
            }
        }
        if (preg_match('/^(.*?)\s+-\s+([\d.]+)s\s+([\d.]+)MB$/', $line, $match)) {
            $row['elapsed'] = $match[2];
            $row['memory'] = $match[3] . ' MB';
            if (preg_match('/^\[([^\]]+)\] (.*)$/', $match[1], $dated)) {
                $row['date'] = $dated[1];
                $row['event'] = $dated[2];
            } else {
                $row['event'] = trim($match[1]);
            }
        }
        return $row;
    }
}
