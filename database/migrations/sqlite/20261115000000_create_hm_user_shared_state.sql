CREATE TABLE IF NOT EXISTS hm_user_shared_state (
    user_key TEXT PRIMARY KEY NOT NULL,
    revision INTEGER NOT NULL,
    payload BLOB NOT NULL
);
