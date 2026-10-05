-- The children of one node, unfiltered — what a move renumbers.
--
-- The topmost level is NULL in the newer rows and 0 in the older ones; the
-- `roots` flag takes both, because `IN`/`=` never matches NULL.

SELECT cms_nodes.*
FROM cms_nodes
WHERE ( node_parent_node_id = :parentId
        OR ( :roots AND ( node_parent_node_id IS NULL OR node_parent_node_id = 0 ) ) )
	-- pages and sites are one family of siblings: a site sits between pages
	-- and is renumbered with them when either moves
	AND ( ISNULL(:nodeType) OR node_type = :nodeType
	      OR ( :nodeType IN ('page', 'site') AND node_type IN ('page', 'site') ) )
ORDER BY node_ordering, node_id

-- :parentId
-- :roots
-- :nodeType
