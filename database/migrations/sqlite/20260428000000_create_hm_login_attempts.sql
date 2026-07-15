CREATE TABLE IF NOT EXISTS hm_login_attempts (
    attempt_key TEXT NOT NULL,
    attempt_count INTEGER DEFAULT 0,
    locked_until INTEGER DEFAULT 0,
    last_attempt INTEGER DEFAULT 0,
    PRIMARY KEY (attempt_key)
);
