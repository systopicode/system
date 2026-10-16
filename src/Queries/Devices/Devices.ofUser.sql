-- The devices of a user that are not revoked - the device list, newest use
-- first. Expired ones stay listed until they are revoked or cleaned up.

SELECT usm_devices.*
FROM usm_devices
WHERE device_user_id = :userId AND device_revoked_at IS NULL
ORDER BY device_last_seen DESC, device_id DESC

-- :userId
