<?php
/** Shared body for global and per-user mail previews. */
(static function (array $entry): void {
    $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
    $status = (string) ($entry['status'] ?? 'unknown');
    $pill = in_array($status, ['error', 'failed'], true) ? 'is-bad' : ($status === 'success' ? 'is-ok' : '');
    $recipients = is_array($entry['recipients'] ?? null) ? $entry['recipients'] : [];
    $stamp = (string) ($entry['timestamp'] ?? '');
    $date = $stamp;
    if ($stamp !== '') {
        try { $date = (new \DateTimeImmutable($stamp))->format(\Systopic\System\Sys\LocalTime::DATETIME_SECONDS); }
        catch (\Exception) { /* Retain unrecognised legacy timestamps verbatim. */ }
    }
    $actor = (string) ($entry['username'] ?? '');
    $actorId = (string) ($entry['userId'] ?? '');
    $actorEmail = (string) ($entry['userEmail'] ?? '');
    $attachments = is_array($entry['attachments'] ?? null) ? $entry['attachments'] : [];
    $error = (string) ($entry['error'] ?? '');
    ?>
    <dl>
        <div><dt>From</dt><dd><?= $e($entry['sender'] ?? '') ?></dd></div>
        <div><dt>To</dt><dd><?= $e(implode(', ', $recipients)) ?></dd></div>
        <div><dt>Date</dt><dd><?= $e($date) ?></dd></div>
        <div><dt>Status</dt><dd><span class="logPill <?= $pill ?>"><?= $e($status) ?></span></dd></div>
        <?php if ($actor !== '' && $actor !== 'not logged in') { ?>
            <div><dt>Sent by</dt><dd><?= $e($actor) ?><?php if ($actorId !== '' && $actorId !== '-') { ?> <span class="logActorId">#<?= $e($actorId) ?></span><?php } ?></dd></div>
        <?php } ?>
        <?php if ($actorEmail !== '' && $actorEmail !== '-') { ?><div><dt>Email</dt><dd><?= $e($actorEmail) ?></dd></div><?php } ?>
        <?php if ($attachments !== []) { ?><div><dt>Attachments</dt><dd><?= $e(implode(', ', $attachments)) ?></dd></div><?php } ?>
    </dl>
    <?php if ($error !== '') { ?><p class="logError"><?= $e($error) ?></p><?php } ?>
    <?php
    $htmlContent = (string) ($entry['htmlContent'] ?? '');
    $textContent = (string) ($entry['textContent'] ?? $entry['contentPreview'] ?? '');
    include __DIR__ . '/preview.tpl.php';
})($mailDetails);
