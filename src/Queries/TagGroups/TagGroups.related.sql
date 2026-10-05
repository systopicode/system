-- Tags that have a page of their own: a page whose template starts with
-- `tagrelated` and that carries the tag.

SELECT tin_tag_id, tin_node_id
FROM cms_tags_in_nodes
JOIN cms_nodes ON node_id = tin_node_id
JOIN cms_pages ON page_node_id = node_id
	AND page_template_name LIKE CONCAT(:template, '%')
	AND page_language_iso = :lang

-- :template
-- :lang
