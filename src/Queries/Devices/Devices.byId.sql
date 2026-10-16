-- One device, by the id its cookie names (Auth\Devices::restore()).

SELECT usm_devices.*
FROM usm_devices
WHERE device_id = :id

-- :id
