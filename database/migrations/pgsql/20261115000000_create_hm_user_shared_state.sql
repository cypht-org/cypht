CREATE TABLE IF NOT EXISTS hm_user_shared_state (
    user_key CHAR(64) PRIMARY KEY NOT NULL,
    revision BIGINT NOT NULL,
    payload BYTEA NOT NULL
);
