-- The failures of one IP (Auth\LoginCodes).

SELECT usm_authfailures.*
FROM usm_authfailures
WHERE authfailure_ip = :ip

-- :ip
