-- A one-time code by the HMAC of what was typed (Auth\SignInCodes).

SELECT usm_signincodes.*
FROM usm_signincodes
WHERE signincode_code_hash = :hash

-- :hash
