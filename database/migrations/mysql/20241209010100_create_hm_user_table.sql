DROP TABLE IF EXISTS hm_user;

CREATE TABLE IF NOT EXISTS hm_user (
    username VARCHAR(255), 
    hash VARCHAR(255), 
    PRIMARY KEY (username)
);
