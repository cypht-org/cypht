CREATE TABLE IF NOT EXISTS hm_login_attempts (
    attempt_key VARCHAR(255) PRIMARY KEY,
    attempt_count INT DEFAULT 0,
    locked_until INT DEFAULT 0,
    last_attempt INT DEFAULT 0
);
