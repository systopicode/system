<?php
use Systopic\System\Maintenance\Runner;
use Systopic\System\Maintenance\Overview;
use Systopic\System\Maintenance\Webcron;
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$state = Overview::state();
$attention = Overview::needsAttention();
$rules = [
    'email-log' => ['After 90 days', 'Delivery troubleshooting. Logged message contents are deleted too. Keep required business correspondence in a separate archive.'],
    'php-log' => ['After 90 days', 'Finding software errors. Older diagnostic entries are no longer kept in this log.'],
    'backup-log' => ['After 90 days', 'Checking backup runs. This deletes the run history, never the backup files.'],
    'login-log' => ['After 365 days', 'Investigating sign-in problems and security incidents. This does not delete user accounts.'],
    'login-codes' => ['Once used or expired', 'A used or expired sign-in code is no longer needed. Valid codes are kept.'],
    'session-markers' => ['After 90 days', 'Removing old temporary session-name records. Active sessions and devices are kept.'],
    'remote-debug' => ['After 24 hours without activity', 'Removing inactive diagnostic browser records and expired debug sessions.'],
];
$modes = ['request' => 'During site visits', 'manual' => 'Started manually'];
if (isset($state['runs']['cli'])) $modes['cli'] = 'Scheduled PHP command';
if (Webcron::enabled() || isset($state['runs']['webcron'])) $modes['webcron'] = 'External scheduler';
?>
<div class="cleanupPanel">
<h1>Cleanup</h1>
<section class="widget"><header><h2><i class="fa-regular fa-<?= $attention ? 'triangle-exclamation' : 'shield-check' ?>"></i>Automatic cleanup</h2></header><main>
<p><?= $e(Overview::summary()) ?></p>
<p><strong>What do I need to do?</strong> <?= $attention ? 'Check the rows marked “Needs attention” below. Use Preview to check the affected data. If the check fails, ask your administrator to inspect the log format, size and file permissions.' : 'No routine action is needed. Cleanup runs automatically during site visits. Check this page occasionally for errors.' ?></p>
<p class="tagsHint">Without visits, cleanup waits. For a rarely visited site, an administrator can set up an optional scheduler below. The periods are system rules, not legal minimum retention periods.</p>
</main></section>
<?= \message::flush() ?>
<?php if (isset($this) && ($this->cleanupPreview ?? null) !== null) { $preview = $this->cleanupPreview; ?>
<section class="widget"><header><h2><i class="fa-regular fa-magnifying-glass"></i>Preview results</h2></header><main>
<p>No data was deleted. These are the expired records eligible for the next cleanup step.</p>
<?php if ($preview['busy']) { ?><p>Another cleanup is running. Please try again shortly.</p><?php } else { ?>
<ul class="cleanupPreviewResults"><?php foreach ($preview['results'] as $id => $entry) { ?><li><strong><?= $e(Runner::jobs()[$id]['label']) ?></strong><span><?= !$entry['ok'] ? 'Could not check this task' : ($entry['count'] === 0 ? 'No expired records found in this step' : $entry['count'] . ' expired records can be removed') ?><?= !empty($entry['more']) ? ' · Further steps may be needed' : '' ?></span></li><?php } ?></ul>
<?php if (!empty($preview['remaining'])) { ?><p>Some tasks were deferred. Preview them individually to check the remaining work.</p><?php } ?>
<?php } ?></main></section>
<?php } ?>
<?php if (isset($this) && ($this->webcronKey ?? null) !== null) { ?>
<section class="widget"><header><h2><i class="fa-regular fa-key"></i>New scheduler key</h2></header><main><label class="field"><span>Copy now — shown only once</span><input type="text" readonly autocomplete="off" spellcheck="false" value="<?= $e($this->webcronKey) ?>" onclick="this.select()"></label><p class="tagsHint">Use this key in the X-Maintenance-Key header over HTTPS. The previous key no longer works.</p></main></section>
<?php } ?>
<section class="widget is-list">
<header><h2>What is deleted — and when?</h2><button type="button" class="button" data-on_click="previewCleanup"><i class="fa-regular fa-magnifying-glass"></i>Preview</button><button type="button" class="button" data-on_click="userConfirm" data-action="run" data-confirm="Remove expired technical data and login-log entries older than <?= (int) \Systopic\System\Maintenance\LogRetention::LOGIN_DAYS ?> days?"><i class="fa-regular fa-broom"></i>Clean up now</button></header>
<div class="tableScroll"><table><thead><tr><th>Data / rule</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach (Runner::jobs() as $id => $job) {
    $last = $state['jobs'][$id] ?? [];
    $rule = $rules[$id] ?? ['When expired', $job['policy']];
    $label = !isset($last['ok']) ? 'Waiting for first run' : (!$last['ok'] ? 'Needs attention' : (!empty($last['more']) ? 'More to clean up' : 'Last check successful'));
?>
<tr><td><strong><?= $e($job['label']) ?></strong><small class="cleanupPolicy"><b><?= $e($rule[0]) ?></b> · <?= $e($rule[1]) ?></small></td><td><span class="cleanupTaskState"><i class="fa-regular fa-<?= !isset($last['ok']) ? 'clock' : (!$last['ok'] ? 'triangle-exclamation' : (!empty($last['more']) ? 'clock' : 'circle-check')) ?>"></i><?= $e($label) ?></span><small class="cleanupPolicy">Last checked: <?= $e(Overview::time((int) ($last['attempt'] ?? 0))) ?></small><?php if (isset($last['ok']) && !$last['ok']) { ?><small class="cleanupPolicy">Next step: preview this rule. Unreadable, oversized or undated logs need administrator review; the original is kept.</small><?php } elseif (!empty($last['more'])) { ?><small class="cleanupPolicy">More records remain. Automatic cleanup will continue; you can also run another step now.</small><?php } ?><?php if (!empty($last['completed']) && $last['completed'] !== ($last['attempt'] ?? 0)) { ?><small class="cleanupPolicy">Last completed: <?= $e(Overview::time((int) $last['completed'])) ?></small><?php } ?></td><td class="icons"><ul><li><button type="button" class="button" data-on_click="previewCleanup" data-job="<?= $e($id) ?>"><i class="fa-regular fa-magnifying-glass"></i>Preview</button></li><li><button type="button" class="button" data-on_click="userConfirm" data-action="run" data-job="<?= $e($id) ?>" data-confirm="Remove expired data for this task?"><i class="fa-regular fa-broom"></i>Clean up</button></li></ul></td></tr>
<?php } ?>
</tbody></table></div>
<main><p class="tagsHint">Preview checks the next group of records without deleting anything. Clean up runs that step immediately. Large backlogs need several steps; automatic cleanup continues on later visits. These are deletion thresholds, not guaranteed exact deletion times.</p></main></section>
<details class="widget cleanupDetails"><summary><i class="fa-regular fa-clock-rotate-left"></i>Execution history<i class="fa-regular fa-chevron-down cleanupChevron"></i></summary><main><dl class="cleanupActivity">
<?php foreach ($modes as $mode => $label) { $run = $state['runs'][$mode] ?? []; ?><dt><?= $e($label) ?></dt><dd><?= $e(Overview::time((int) ($run['time'] ?? 0))) ?><?php if ($run) { ?><small><?= !$run['ok'] ? 'Failed — check the tasks above' : (!empty($run['remaining']) ? 'Completed one step; other tasks will follow' : 'Completed') ?></small><?php if (!$run['ok'] && Overview::successTime($run) > 0) { ?><small>Last successful run: <?= $e(Overview::time(Overview::successTime($run))) ?></small><?php } ?><?php } ?></dd><?php } ?>
</dl><p class="tagsHint">Automatic cleanup checks for work at most every five minutes. Completed tasks are normally checked again after one hour. Refresh this page to see a run that finished after your last visit.</p></main></details>
<details class="widget cleanupDetails cleanupSetup"><summary><i class="fa-regular fa-clock"></i>Optional automatic scheduling<i class="fa-regular fa-chevron-down cleanupChevron"></i></summary><main>
<p>For sites with few visits, a scheduled task can run cleanup regularly. Choose a PHP command (CLI) or an external HTTPS request (Webcron). A manual run or preview does not verify that your scheduler works.</p>
<h3>PHP command</h3><p>From this project's <code>public</code> directory, run <code>php index.php @/path/to/maintenance.json</code>. The JSON file contains <code>{"maintenance":true,"instance":"YOUR_INSTANCE"}</code>. Use your installation's PHP executable and exact environment. Add <code>"preview":true</code> for a test without deleting data or updating activity.</p>
<h3>External HTTPS request</h3><p>Access: <strong><?= Webcron::enabled() ? 'Enabled' : 'Disabled' ?></strong>. Send an HTTPS POST to <code><?= $e(preg_replace('~^http://~i', 'https://', rtrim((string) \http::$siteUrl, '/')) . '/_maintenance/') ?></code> with the key in the <code>X-Maintenance-Key</code> header. HTTPS is required. Never put the key in the URL.</p>
<div class="cleanupRowActions"><button type="button" class="button" data-on_click="userConfirm" data-action="rotateWebcron" data-confirm="Create a new scheduler key? Any previous key stops working."><i class="fa-regular fa-key"></i><?= Webcron::enabled() ? 'Replace key' : 'Create key' ?></button>
<?php if (Webcron::enabled()) { ?><button type="button" class="button" data-on_click="userConfirm" data-action="disableWebcron" data-confirm="Disable external scheduler access?"><i class="fa-regular fa-stop"></i>Disable access</button><?php } ?></div>
<p class="tagsHint">Creating a key does not install a scheduler. Your scheduler must support POST and custom headers. Behind a TLS proxy, the server must securely communicate HTTPS to PHP.</p>
</main></details>
<p class="tagsHint cleanupScope">Cleanup keeps accounts, active devices, content, uploads and backup archives. Required business correspondence needs a separate archive. Undated legacy logs need administrator review.</p>
</div>
