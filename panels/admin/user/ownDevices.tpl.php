<?php
use Systopic\System\Auth\Devices;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
$me = $this->me;
if (!$me) return;
$here = Devices::current();
$screen = null;
$ownDevices = DeviceQuery::ofUser($this->scope, (int) $me->id);
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static fn($time): string => \Systopic\System\Sys\LocalTime::format($time, \Systopic\System\Sys\LocalTime::DATETIME);
?>
<?php if ($ownDevices === []) { ?><p class="profileEmpty">No devices yet.</p><?php } ?>
<?php foreach (array_slice($ownDevices, 0, 5) as $device) { include __DIR__ . '/deviceRow.tpl.php'; } ?>
