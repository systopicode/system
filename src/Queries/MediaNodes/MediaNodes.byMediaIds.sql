-- Media by their own ids - the folders of a file (var/original/001/53/ is
-- media 153) name the medium, the node is what a reference points at.

SELECT cms_medias.*, cms_nodes.*
FROM cms_medias
JOIN cms_nodes ON node_id = media_node_id
WHERE media_id IN (:mediaIds)

-- :mediaIds
