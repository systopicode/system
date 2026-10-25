-- A one-time code by the HMAC of what was typed (Auth\LoginCodes).

SELECT usm_login_codes.*
FROM usm_login_codes
WHERE login_code_code_hash = :hash

-- :hash
