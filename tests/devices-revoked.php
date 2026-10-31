<?php
require __DIR__ . '/bootstrap.php';
$db=new PDO('sqlite::memory:');
$db->exec('CREATE TABLE usm_devices (device_id INTEGER, device_user_id INTEGER, device_revoked_at TEXT, device_last_seen TEXT)');
$db->exec("INSERT INTO usm_devices VALUES (1,19,NULL,'2026-10-30'),(2,19,'2026-10-30','2026-10-29'),(3,20,NULL,'2026-10-30')");
$sql=file_get_contents(__DIR__.'/../src/Queries/Devices/Devices.ofUser.sql');
$q=$db->prepare($sql);$q->execute(['userId'=>19,'includeRevoked'=>0]);
check('normal list excludes revoked devices',array_column($q->fetchAll(PDO::FETCH_ASSOC),'device_id')===[1]);
$q->execute(['userId'=>19,'includeRevoked'=>1]);
check('blocked view may include recorded revoked devices of this user only',array_column($q->fetchAll(PDO::FETCH_ASSOC),'device_id')===[1,2]);
