DROP TABLE IF EXISTS hm_user_session;

CREATE TABLE IF NOT EXISTS hm_user_session (
    hm_id VARCHAR(255), 
    data LONGBLOB, 
    date TIMESTAMP, 
    PRIMARY KEY (hm_id)
);
