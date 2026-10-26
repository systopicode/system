-- The pairing a screen asks after, by the hash of its poll secret.

SELECT usm_logincodes.*
FROM usm_logincodes
WHERE logincode_poll_hash = :hash

-- :hash
