-- Media the maintenance page looks at: without a usable mime type, or images
-- without a size. Small tables — limited in PHP.

SELECT cms_medias.*
FROM cms_medias
WHERE (
		:kind = 'mimetype'
		AND ( ISNULL(media_mimetype) OR media_mimetype = '' OR media_mimetype LIKE 'nosupport:%' )
		AND media_type <> 'image360'
	) OR (
		:kind2 = 'size'
		AND ( ISNULL(media_width) OR media_width = 0 OR ISNULL(media_height) OR media_height = 0 )
		AND media_type = 'image'
	)
ORDER BY media_id

-- :kind
-- :kind2
