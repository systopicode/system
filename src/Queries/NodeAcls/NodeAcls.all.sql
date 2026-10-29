-- Every node with what inheritance needs and nothing else: what the repair
-- walks top-down.

SELECT node_id, node_parent_node_id, node_acl_id, node_acl_inherit
FROM cms_nodes

