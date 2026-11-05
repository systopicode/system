<?php
require __DIR__ . '/bootstrap.php';
use Systopic\System\Backup\LogEntry;
use Systopic\System\Maintenance\RetainedLogs;

$time = new DateTimeImmutable('2026-11-04T18:00:00+01:00');
$name = "Jörg ] \"Admin\"\n<script>";
$line = LogEntry::format("Backup Created\nnext", 42, $name, 1.25, 24.5, $time);
$row = LogEntry::parse($line);
check('new entry retains actor id and exact display name', $row['userId'] === 42 && $row['user'] === $name);
check('one record per event despite control characters', substr_count($line, "\n") === 1 && $row['event'] === 'Backup Created next');
check('date, runtime and memory survive actor metadata', $row['date'] === $time->format('c') && $row['elapsed'] === '1.25' && $row['memory'] === '24.5 MB');
check('retention recognises the unchanged dated prefix', RetainedLogs::timestamp($line, 'backup') === $time->getTimestamp());
$old = LogEntry::parse('[2026-11-02T12:00:00+01:00] Backup Created - 1.2s 25MB');
check('dated old entries keep event and have no invented user', $old['event'] === 'Backup Created' && $old['user'] === '' && $old['date'] === '2026-11-02T12:00:00+01:00');
$legacy = LogEntry::parse('task List created - 1750000000.25s 10MB');
check('legacy clock entries remain readable', $legacy['elapsed'] === '1750000000.25' && $legacy['user'] === '');
$raw = LogEntry::parse('unstructured legacy record');
check('unstructured records remain intact', $raw['event'] === 'unstructured legacy record' && $raw['user'] === '');
$bad = LogEntry::parse("record\tuser={bad}");
check('malformed actor metadata stays visible', $bad['event'] === "record\tuser={bad}" && $bad['user'] === '');
$recovery = LogEntry::parse(LogEntry::format('Backup Restored', 1, 'systemRecovery', 0.5, 8, $time));
check('recovery actor is retained explicitly', $recovery['userId'] === 1 && $recovery['user'] === 'systemRecovery');
