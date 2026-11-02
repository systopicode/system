-- The roles that have a key: the system's own ones are among them
-- (Roles\Operator::BUILTIN, ::SYSTEM). Names role_key, so a database without
-- it gets the column here, and the migration with it (Migrations\RoleKey).

SELECT usm_roles.*
FROM usm_roles
WHERE role_key IS NOT NULL
