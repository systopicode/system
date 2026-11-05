<?php
use Systopic\System\Auth\SignInGrant;
use Systopic\System\Auth\Devices;
use Systopic\System\Auth\DeviceName;
use Systopic\System\Auth\Presence;
use Systopic\System\Auth\SignInLog;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
$me = $this->me;
if (!$me || !SignInGrant::mayScreens($this)) return;
$here = Devices::current();
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static fn($time): string => \Systopic\System\Sys\LocalTime::format($time, \Systopic\System\Sys\LocalTime::DATETIME);
$screenDevices = [];
foreach (SignInGrant::accounts($this->scope, $me, $this) as $screen) {
    if (!$screen['screen']) continue;
    foreach (DeviceQuery::ofUser($this->scope, $screen['id']) as $device) $screenDevices[] = [$device, $screen];
}
usort($screenDevices, static fn(array $a, array $b): int => $b[0]->lastSeen <=> $a[0]->lastSeen);
?>
<p class="tagsHint">Devices that are signed in as a screen.</p>
<?php if ($screenDevices === []) { ?><p class="profileEmpty">No devices paired yet.</p><?php } ?>
<?php foreach ($screenDevices as [$device, $screen]) { include __DIR__ . '/deviceRow.tpl.php'; } ?>
