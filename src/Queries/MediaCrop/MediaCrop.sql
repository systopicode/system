-- A medium with its crop for one format — what making a format needs
-- (mediaImage) and what the crop editor shows (mediaEditor). No crop yet: the
-- crop columns are NULL.

SELECT cms_medias.*, cms_media_crops.*
FROM cms_medias
LEFT JOIN cms_media_crops ON media_crop_media_id = media_id AND media_crop_format = :format
WHERE media_id = :mediaId

-- :mediaId
-- :format
