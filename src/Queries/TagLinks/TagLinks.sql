-- Every link of one tag — the junction rows themselves, to be moved or removed.

SELECT cms_tags_in_nodes.*
FROM cms_tags_in_nodes
WHERE tin_tag_id = :tagId

-- :tagId
