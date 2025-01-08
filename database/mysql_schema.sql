CREATE TABLE IF NOT EXISTS hm_user (
    username VARCHAR(255), 
    hash VARCHAR(255), 
    PRIMARY KEY (username)
);

CREATE TABLE IF NOT EXISTS hm_user_session (
    hm_id VARCHAR(255), 
    data LONGBLOB, 
    date TIMESTAMP, 
    hm_version INT DEFAULT 1,
    PRIMARY KEY (hm_id)
);

CREATE TABLE IF NOT EXISTS hm_user_settings (
    username VARCHAR(255), 
    settings LONGBLOB, 
    PRIMARY KEY (username)
);