-- Media by the ids of their nodes — the share image of a page, a medium a
-- text links to. Trashed ones too: whoever holds the id decides.

SELECT cms_medias.*, cms_nodes.*
FROM cms_medias
JOIN cms_nodes ON node_id = media_node_id
WHERE node_id IN (:nodeIds)

-- :nodeIds
