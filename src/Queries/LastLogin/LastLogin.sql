-- The latest login of a user, from the request log.

SELECT log_requests.request_id, log_requests.request_datetime
FROM log_requests
WHERE request_type = 'L' AND request_user_id = :userId
ORDER BY request_datetime DESC
LIMIT 1

-- :userId
