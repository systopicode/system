-- One user, found the way the login forms and the profile ask: by id, by
-- activation key, or by name or email (the password is checked in PHP,
-- Auth\Session::check()). User 1 is the system's own and never found.

SELECT usm_users.*
FROM usm_users
WHERE user_id != 1 AND CASE
    WHEN :id IS NOT NULL THEN user_id = :id
    WHEN :key IS NOT NULL THEN user_activation_key = :key
    ELSE user_name = :name OR user_email = :name
END
ORDER BY user_id
LIMIT 1

-- :id
-- :key
-- :name
