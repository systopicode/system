<?php
use Systopic\System\Maintenance\Runner;
use Systopic\System\Maintenance\Overview;
use Systopic\System\Maintenance\Webcron;
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$maintenanceState = Overview::state();
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
if (isset($maintenanceState['runs']['cli'])) $modes['cli'] = 'Scheduled PHP command';
if (Webcron::enabled() || isset($maintenanceState['runs']['webcron'])) $modes['webcron'] = 'External scheduler';
?>
<div class="cleanupPanel">
<h1>Cleanup</h1>
<section class="widget"><header><h2>Automatic cleanup</h2></header><main>
<p class="cleanupOverview <?= $attention ? 'is-warning' : 'is-success' ?>"><i class="cmsIcon <?= $attention ? 'is-warning' : 'is-success' ?> <?= $attention ? 'cmsIcon-circle-exclamation' : 'cmsIcon-circle-check' ?>"></i> <?= $e(Overview::summary()) ?></p>
<p><strong>What do I need to do?</strong> <?= $attention ? 'Check the rows marked “Needs attention” below. Use Preview to check the affected data. If the check fails, ask your administrator to inspect the log format, size and file permissions.' : 'No routine action is needed. Cleanup runs automatically during site visits. Check this page occasionally for errors.' ?></p>
<p class="tagsHint">Without visits, cleanup waits. For a rarely visited site, an administrator can set up an optional scheduler below. The periods are system rules, not legal minimum retention periods.</p>
</main></section>
<?= \message::flush() ?>
<?php include __DIR__ . '/preview.tpl.php'; ?>
<?php if (isset($this) && ($this->webcronKey ?? null) !== null) { ?>
<section class="widget"><header><h2>New scheduler key</h2></header><main><label class="field"><span>Copy now — shown only once</span><input type="text" readonly autocomplete="off" spellcheck="false" value="<?= $e($this->webcronKey) ?>" onclick="this.select()"></label><p class="tagsHint">Use this key in the X-Maintenance-Key header over HTTPS. The previous key no longer works.</p></main></section>
<?php } ?>
<section class="widget is-list">
<header><h2>What is deleted — and when?</h2><button type="button" class="button" data-on_click="previewCleanup"><i class="cmsIcon cmsIcon-magnifying-glass"></i>Preview</button><button type="button" class="button" data-on_click="userConfirm" data-action="run" data-confirm="Remove expired technical data and login-log entries older than <?= (int) \Systopic\System\Maintenance\LogRetention::LOGIN_DAYS ?> days?"><i class="cmsIcon cmsIcon-broom"></i>Clean up now</button></header>
<div class="tableScroll" data-table-scroll><table><thead><tr><th>Data / rule</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach (Runner::jobs() as $id => $job) {
    $last = $maintenanceState['jobs'][$id] ?? [];
    $rule = $rules[$id] ?? ['When expired', $job['policy']];
    $label = !isset($last['ok']) ? 'Waiting for first run' : (!$last['ok'] ? 'Needs attention' : (!empty($last['more']) ? 'More to clean up' : 'Last check successful'));
?>
<tr><td><strong><?= $e($job['label']) ?></strong><small class="cleanupPolicy"><b><?= $e($rule[0]) ?></b> · <?= $e($rule[1]) ?></small></td><td><span class="cleanupTaskState"><i class="cmsIcon <?= !isset($last['ok']) ? 'is-warning' : (!$last['ok'] ? 'is-error' : (!empty($last['more']) ? 'is-warning' : 'is-success')) ?> cmsIcon-<?= !isset($last['ok']) ? 'clock' : (!$last['ok'] ? 'triangle-exclamation' : (!empty($last['more']) ? 'clock' : 'circle-check')) ?>"></i><?= $e($label) ?></span><small class="cleanupPolicy">Last checked: <?= $e(Overview::time((int) ($last['attempt'] ?? 0))) ?></small><?php if (isset($last['ok']) && !$last['ok']) { ?><small class="cleanupPolicy">Next step: preview this rule. Unreadable, oversized or undated logs need administrator review; the original is kept.</small><?php } elseif (!empty($last['more'])) { ?><small class="cleanupPolicy">More records remain. Automatic cleanup will continue; you can also run another step now.</small><?php } ?><?php if (!empty($last['completed']) && $last['completed'] !== ($last['attempt'] ?? 0)) { ?><small class="cleanupPolicy">Last completed: <?= $e(Overview::time((int) $last['completed'])) ?></small><?php } ?></td><td class="icons"><ul><li><button type="button" class="button icononly" title="Preview" aria-label="Preview" data-on_click="previewCleanup" data-job="<?= $e($id) ?>"><i class="cmsIcon cmsIcon-magnifying-glass"></i></button></li><li><button type="button" class="button icononly" title="Clean up" aria-label="Clean up" data-on_click="userConfirm" data-action="run" data-job="<?= $e($id) ?>" data-confirm="Remove expired data for this task?"><i class="cmsIcon cmsIcon-broom"></i></button></li></ul></td></tr>
<?php } ?>
</tbody></table></div>
<main><p class="tagsHint">Preview checks the next group of records without deleting anything. Clean up runs that step immediately. Large backlogs need several steps; automatic cleanup continues on later visits. These are deletion thresholds, not guaranteed exact deletion times.</p></main></section>
<details class="widget cleanupDetails"><summary>Execution history</summary><main><dl class="cleanupActivity">
<?php foreach ($modes as $mode => $label) { $run = $maintenanceState['runs'][$mode] ?? []; ?><dt><?= $e($label) ?></dt><dd><?= $e(Overview::time((int) ($run['time'] ?? 0))) ?><?php if ($run) { ?><small><?= !$run['ok'] ? 'Failed — check the tasks above' : (!empty($run['remaining']) ? 'Completed one step; other tasks will follow' : 'Completed') ?></small><?php if (!$run['ok'] && Overview::successTime($run) > 0) { ?><small>Last successful run: <?= $e(Overview::time(Overview::successTime($run))) ?></small><?php } ?><?php } ?></dd><?php } ?>
</dl><p class="tagsHint">Automatic cleanup checks for work at most every five minutes. Completed tasks are normally checked again after one hour. Refresh this page to see a run that finished after your last visit.</p></main></details>
<details class="widget cleanupDetails cleanupSetup"><summary>Optional automatic scheduling</summary><main>
<p>Optional: cleanup already runs during site visits. For a site with few visits, configure one of the two methods below in your hosting panel or scheduler. You only need one. After its first run, check Recent activity above.</p>
<details class="cmsBox cleanupMethod"><summary>Option 1: PHP command (server cron)</summary><p>From this project's <code>public</code> directory, run <code>php index.php @/path/to/maintenance.json</code>. The JSON file contains <code>{"maintenance":true,"instance":"YOUR_INSTANCE"}</code>. Use your installation's PHP executable and exact environment. Add <code>"preview":true</code> for a test without deleting data or updating activity.</p>
</details><details class="cmsBox cleanupMethod"><summary>Option 2: HTTPS request (Webcron)</summary><p>Access: <strong><?= Webcron::enabled() ? 'Enabled' : 'Disabled' ?></strong>. Send an HTTPS POST to <code><?= $e(preg_replace('~^http://~i', 'https://', rtrim((string) \http::$siteUrl, '/')) . '/_maintenance/') ?></code> with the key in the <code>X-Maintenance-Key</code> header. HTTPS is required. Never put the key in the URL.</p>
<div class="cleanupRowActions"><button type="button" class="button" data-on_click="userConfirm" data-action="rotateWebcron" data-confirm="Create a new scheduler key? Any previous key stops working."><i class="cmsIcon cmsIcon-key"></i><?= Webcron::enabled() ? 'Replace key' : 'Create key' ?></button>
<?php if (Webcron::enabled()) { ?><button type="button" class="button" data-on_click="userConfirm" data-action="disableWebcron" data-confirm="Disable external scheduler access?"><i class="cmsIcon cmsIcon-stop"></i>Disable access</button><?php } ?></div>
<p class="tagsHint">Creating a key does not install a scheduler. Your scheduler must support POST and custom headers. Behind a TLS proxy, the server must securely communicate HTTPS to PHP.</p>
</details></main></details>
<p class="tagsHint cleanupScope">Cleanup keeps accounts, active devices, content, uploads and backup archives. Required business correspondence needs a separate archive. Undated legacy logs need administrator review.</p>
</div>
