-- The pairing a screen asks after, by the hash of its poll secret.

SELECT usm_login_codes.*
FROM usm_login_codes
WHERE logincode_poll_hash = :hash

-- :hash
