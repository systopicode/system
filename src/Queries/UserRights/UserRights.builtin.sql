-- The roles the system gives on its own: everyone, authenticated.

SELECT usm_roles.*
FROM usm_roles
WHERE role_builtin IS NOT NULL
