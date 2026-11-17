-- The media themselves, for their crops: size, points of interest, the
-- master's size.
--
-- The COALESCE names the columns that came with the master and is always
-- true - see MediaCrops.sql.

SELECT cms_medias.*
FROM cms_medias
WHERE media_id IN (:mediaIds)
	AND COALESCE(media_pois, media_master_width, media_master_height, 1) IS NOT NULL

-- :mediaIds
