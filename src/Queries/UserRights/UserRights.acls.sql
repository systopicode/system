-- What a set of roles may do with the ACLs: an ACL may be read (written) when
-- one of its groups may — the most any of the roles grants on any of them.

SELECT
	gia_acl_id AS rights_acl_id,
	MAX(rog_read) AS rights_read,
	MAX(rog_write) AS rights_write
FROM usm_groups_in_acls
JOIN usm_roles_on_groups ON rog_group_id = gia_group_id
WHERE rog_role_id IN (:roleIds)
GROUP BY gia_acl_id
ORDER BY gia_acl_id

-- :roleIds
