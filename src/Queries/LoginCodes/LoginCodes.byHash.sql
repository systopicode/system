-- A one-time code by the HMAC of what was typed (Auth\LoginCodes).

SELECT usm_logincodes.*
FROM usm_logincodes
WHERE logincode_code_hash = :hash

-- :hash
