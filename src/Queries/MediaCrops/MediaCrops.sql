-- Every crop row of some media - the master's (format 'original'), the crops
-- of the ratios, and what is left from the named formats.
--
-- The COALESCE names every column of the new form and is always true: on a
-- database from before, this statement is where the provisioner adds them
-- (saving a row does not provision, so they have to be there first).

SELECT cms_media_crops.*
FROM cms_media_crops
WHERE media_crop_media_id IN (:mediaIds)
	AND COALESCE(media_crop_ratio, media_crop_center_x, media_crop_center_y, media_crop_scale, media_crop_edit, 1) IS NOT NULL
ORDER BY media_crop_media_id, media_crop_id

-- :mediaIds
