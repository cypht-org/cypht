PRAGMA foreign_keys=off;
CREATE TEMPORARY TABLE hm_user_session_new AS
SELECT *, NULL AS lock FROM hm_user_session;

DROP TABLE hm_user_session;
ALTER TABLE hm_user_session_new RENAME TO hm_user_session;
PRAGMA foreign_keys=on;