
DROP TABLE IF EXISTS hm_user;

DROP TABLE IF EXISTS hm_user_session;

DROP TABLE IF EXISTS hm_user_settings;

CREATE TABLE IF NOT EXISTS hm_user (username varchar(255), hash varchar(255), primary key (username));

CREATE TABLE IF NOT EXISTS hm_user_session (hm_id varchar(255), data bytea, date timestamp, hm_version int default 1, primary key (hm_id));

CREATE TABLE IF NOT EXISTS hm_user_settings(username varchar(255), settings bytea, primary key (username));
