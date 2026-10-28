-- Every device that is not revoked. Online is decided by the hub, not here.

SELECT usm_devices.*
FROM usm_devices
WHERE device_revoked_at IS NULL
ORDER BY device_last_seen DESC, device_id DESC
