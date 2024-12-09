DROP TABLE IF EXISTS hm_user_session;

CREATE TABLE IF NOT EXISTS hm_user_session (
    hm_id TEXT NOT NULL, 
    data BLOB, 
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    PRIMARY KEY (hm_id)
);

