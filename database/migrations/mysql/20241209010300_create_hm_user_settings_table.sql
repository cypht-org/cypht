DROP TABLE IF EXISTS hm_user_settings;

CREATE TABLE IF NOT EXISTS hm_user_settings (
    username VARCHAR(255), 
    settings LONGBLOB, 
    PRIMARY KEY (username)
);
