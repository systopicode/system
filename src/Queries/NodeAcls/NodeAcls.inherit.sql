-- Check the node flag and both tables used by ACL writes.
SELECT cms_nodes.node_acl_inherit
FROM cms_nodes
LEFT JOIN usm_acls ON acl_id = node_acl_id
LEFT JOIN usm_groups_in_acls ON gia_acl_id = acl_id
WHERE node_id = :nodeId
LIMIT 1
