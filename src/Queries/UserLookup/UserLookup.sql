-- One user, found the way the login forms and the profile ask: by id, by
-- activation key, or by name or email (the password is checked in PHP,
-- Auth\Session::check()). User 1 is the system's own and never found.
--
-- A screen (user_type 'screen') is found by id only: it has no password and
-- no mail, so a login form must never reach it. The condition also names
-- user_type in every call - on an installation from before the column, the
-- first request fails with 1054 and the provisioner adds it (UserLookup.php).

SELECT usm_users.*
FROM usm_users
WHERE user_id != 1
    AND (:id IS NOT NULL OR COALESCE(user_type, 'person') != 'screen')
    AND CASE
        WHEN :id IS NOT NULL THEN user_id = :id
        WHEN :key IS NOT NULL THEN user_activation_key = :key
        ELSE user_name = :name OR user_email = :name
    END
ORDER BY user_id
LIMIT 1

-- :id
-- :key
-- :name
