-- The ACL of exactly one set of groups, by its key: the sorted group ids.

SELECT usm_acls.*
FROM usm_acls
WHERE acl_group_ids = :groupIds

-- :groupIds
