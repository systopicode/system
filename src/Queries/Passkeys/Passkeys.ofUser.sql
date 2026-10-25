SELECT usm_passkeys.* FROM usm_passkeys WHERE passkey_user_id = :userId AND (:project IS NULL OR passkey_project = :project) ORDER BY passkey_id DESC
