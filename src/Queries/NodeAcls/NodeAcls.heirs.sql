-- The nodes below one node that inherit its ACL: children that inherit, their
-- children that inherit, and so on. A node with an ACL of its own ends the
-- branch — neither it nor anything below it is in the list.
--
-- UNION and not UNION ALL: a cycle in node_parent_node_id (broken data) ends
-- the recursion instead of running it into the limit — MySQL stops at 1000
-- levels with an error, MariaDB only after billions of iterations.

WITH RECURSIVE heirs (id) AS (
	SELECT node_id FROM cms_nodes
	WHERE node_parent_node_id = :nodeId AND node_acl_inherit = 1
	UNION
	SELECT child.node_id
	FROM cms_nodes child
	JOIN heirs ON child.node_parent_node_id = heirs.id
	WHERE child.node_acl_inherit = 1
)
SELECT cms_nodes.node_id, cms_nodes.node_parent_node_id, cms_nodes.node_acl_id, cms_nodes.node_acl_inherit
FROM heirs
JOIN cms_nodes ON cms_nodes.node_id = heirs.id

-- :nodeId
