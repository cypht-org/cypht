PRAGMA foreign_keys=off;

CREATE TABLE hm_user_session_new (
    hm_id TEXT NOT NULL, 
    data BLOB, 
    date TIMESTAMP, 
    hm_version INTEGER DEFAULT 1, 
    PRIMARY KEY (hm_id)
);

INSERT INTO hm_user_session_new (hm_id, data, date)
SELECT hm_id, data, date 
FROM hm_user_session;

DROP TABLE hm_user_session;
ALTER TABLE hm_user_session_new RENAME TO hm_user_session;

PRAGMA foreign_keys=on;
