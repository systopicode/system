-- The media hanging below several nodes — a page's media for a whole tree
-- level in one statement (the legacy `medias::get($page)` asked once per page).
--
-- No filter on the node type, as in the legacy medias.sql: a media folder
-- below a page carries a cms_medias row too. Order: per parent, by place.

SELECT cms_medias.*, cms_nodes.*
FROM cms_medias
JOIN cms_nodes ON node_id = media_node_id
WHERE node_parent_node_id IN (:parentIds)
	AND ( !node_is_trashed OR :showtrashed )
ORDER BY node_parent_node_id, node_ordering

-- :parentIds
-- :showtrashed
