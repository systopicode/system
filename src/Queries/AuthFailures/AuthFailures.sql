-- The failures of one IP (Auth\LoginCodes).

SELECT usm_auth_failures.*
FROM usm_auth_failures
WHERE auth_failure_ip = :ip

-- :ip
