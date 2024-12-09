PRAGMA foreign_keys=off;

CREATE TABLE hm_user_session_new (
    hm_id TEXT PRIMARY KEY, 
    data BLOB, 
    date TIMESTAMP, 
    hm_version INTEGER DEFAULT 1,
    lock INTEGER DEFAULT 0
);

INSERT INTO hm_user_session_new (hm_id, data, date, hm_version)
SELECT hm_id, data, date, hm_version
FROM hm_user_session;

DROP TABLE hm_user_session;

ALTER TABLE hm_user_session_new RENAME TO hm_user_session;

PRAGMA foreign_keys=on;
