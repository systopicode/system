<?php
/** The requesting device, using the same row presentation as the device list. */
use Systopic\System\Auth\DeviceName;
$agent = (string) $pending->userAgent;
$metadata = array_filter([
    (string) $pending->ip,
    $pending->createdAt ? \Systopic\System\Sys\LocalTime::format($pending->createdAt, \Systopic\System\Sys\LocalTime::DATETIME) : '',
]);
?>
<div class="userDevice deviceRequest">
    <i class="<?= $e(DeviceName::icon($agent)) ?>" aria-hidden="true"></i>
    <span><strong><?= $e(DeviceName::from($agent)) ?></strong><small><?= $e(implode(' · ', $metadata)) ?></small></span>
</div>
