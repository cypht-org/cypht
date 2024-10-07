
CREATE TABLE IF NOT EXISTS hm_user (username varchar(255), hash varchar(255), primary key (username));

CREATE TABLE IF NOT EXISTS hm_user_session (hm_id varchar(255), data longblob, date timestamp, primary key (hm_id));

CREATE TABLE IF NOT EXISTS hm_user_settings(username varchar(255), settings longblob, primary key (username));
