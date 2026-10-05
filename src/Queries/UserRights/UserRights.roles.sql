-- The roles of one user — or every role (:all), what a superuser holds.
-- Row 1 of usm_roles is the empty default record, no role.

SELECT usm_roles.*
FROM usm_roles
WHERE role_id != 1 AND role_name <> '' AND (
	:all OR role_id IN (SELECT uar_role_id FROM usm_users_at_roles WHERE uar_user_id = :userId)
)
ORDER BY role_id

-- :all
-- :userId
