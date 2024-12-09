DROP TABLE IF EXISTS hm_user;

CREATE TABLE IF NOT EXISTS hm_user (
    username TEXT NOT NULL, 
    hash TEXT NOT NULL, 
    PRIMARY KEY (username)
);
