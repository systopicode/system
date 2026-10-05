-- The media library of a page: every media node below it — media, media
-- folders, and aliases of media elsewhere — with the medium it shows (its own,
-- or its original's) and, for an alias, where the original lives.
--
-- The successor of lib/class/mml/mml.sql. Trashed nodes are shown, as there.

SELECT
	cms_nodes.*,
	cms_medias.*,
	original.node_parent_node_id AS library_original_parent_id
FROM cms_nodes
LEFT JOIN cms_medias ON media_node_id = IF(COALESCE(cms_nodes.node_original_node_id, 0) = 0, cms_nodes.node_id, cms_nodes.node_original_node_id)
LEFT JOIN cms_nodes AS original ON original.node_id = cms_nodes.node_original_node_id
WHERE cms_nodes.node_parent_node_id = :parentId
	AND cms_nodes.node_type IN ('media', 'medias')
ORDER BY cms_nodes.node_ordering, cms_nodes.node_id

-- :parentId
