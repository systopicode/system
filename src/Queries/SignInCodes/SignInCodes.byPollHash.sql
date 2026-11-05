-- The device code a device asks after, by the hash of its poll secret.

SELECT usm_signincodes.*
FROM usm_signincodes
WHERE signincode_poll_hash = :hash

-- :hash
