<?php
/** @var \Systopic\System\Panels\PanelNode $this */
use Systopic\System\Maintenance\Runner;
if (!in_array($this->action, ['preview', 'previewCleanup', 'run', 'rotateWebcron', 'disableWebcron'], true)) return;
if (!\Systopic\System\Auth\Session::isSuperuser()) { \message::error('Only a superuser can run cleanup.'); return; }
if (!in_array(strtoupper((string) \http::$method), ['POST', 'PUT'], true)) { \message::error('Use the cleanup controls to start a run.'); return; }
try {
    if ($this->action === 'rotateWebcron') {
        $this->webcronKey = \Systopic\System\Maintenance\Webcron::rotate();
        \message::confirm('Webcron key replaced. Copy the new key from the field shown below.');
        $this->updateView('main');
        return;
    }
    if ($this->action === 'disableWebcron') {
        \Systopic\System\Maintenance\Webcron::disable();
        \message::confirm('Webcron disabled.');
        $this->updateView('main');
        return;
    }
    $job = (string) \http::dataset('job');
    $preview = in_array($this->action, ['preview', 'previewCleanup'], true);
    $result = Runner::run('manual', $job !== '' ? $job : null, $preview);
    if ($preview) $this->cleanupPreview = $result;
    if ($result['busy'] && !$preview) { \message::error('Another cleanup is running. Please try again shortly.'); }
    elseif (!$result['busy'] && !$preview) {
        if (($result['remaining'] ?? []) !== []) \message::warning('The time budget was reached. Not every task ran; run another batch, or preview tasks individually.');
        foreach ($result['results'] as $id => $entry) {
            $label = Runner::jobs()[$id]['label'];
            if (!$entry['ok']) { \message::error($label . ': failed. Check project permissions and logs.'); continue; }
            \message::confirm($label . ': ' . $entry['count'] . ($preview ? ' eligible in this batch.' : ' removed in this batch.') . ($entry['more'] ? ' More work remains; run another batch.' : ''));
        }
    }
} catch (\Throwable) { \message::error('Cleanup could not run. Check maintenance storage and project permissions.'); }
$this->updateView('main');
