CREATE TABLE IF NOT EXISTS hm_user (
    username TEXT NOT NULL, 
    hash TEXT NOT NULL, 
    PRIMARY KEY (username)
);

CREATE TABLE IF NOT EXISTS hm_user_session (
    hm_id TEXT NOT NULL, 
    data BLOB, 
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    hm_version INT DEFAULT 1,
    lock INT DEFAULT 0,
    PRIMARY KEY (hm_id)
);

CREATE TABLE IF NOT EXISTS hm_user_settings (
    username TEXT NOT NULL, 
    settings BLOB, 
    PRIMARY KEY (username)
);
