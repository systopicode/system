-- What a set of roles may do with the groups: read and write, the most any
-- of the roles grants (lib/class/user/permissions.sql).

SELECT
	rog_group_id AS rights_group_id,
	MAX(rog_read) AS rights_read,
	MAX(rog_write) AS rights_write
FROM usm_roles_on_groups
WHERE rog_role_id IN (:roleIds)
GROUP BY rog_group_id
ORDER BY rog_group_id

-- :roleIds
