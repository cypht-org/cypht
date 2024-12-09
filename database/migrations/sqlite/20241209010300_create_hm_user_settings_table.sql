DROP TABLE IF EXISTS hm_user_settings;

CREATE TABLE IF NOT EXISTS hm_user_settings (
    username TEXT NOT NULL, 
    settings BLOB, 
    PRIMARY KEY (username)
);
