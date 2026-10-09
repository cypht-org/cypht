
DROP TABLE IF EXISTS hm_user;

DROP TABLE IF EXISTS hm_user_session;

DROP TABLE IF EXISTS hm_user_settings;

DROP TABLE IF EXISTS hm_login_attempts;

CREATE TABLE IF NOT EXISTS hm_user (username varchar(255), hash varchar(255), primary key (username));

CREATE TABLE IF NOT EXISTS hm_user_session (hm_id varchar(255), data bytea, date timestamp, hm_version int default 1, primary key (hm_id));

CREATE TABLE IF NOT EXISTS hm_user_settings(username varchar(255), settings bytea, primary key (username));

CREATE TABLE IF NOT EXISTS hm_login_attempts (attempt_key varchar(255), attempt_count int default 0, locked_until int default 0, last_attempt int default 0, primary key (attempt_key));

DROP TABLE IF EXISTS hm_mcp_settings;

DROP TABLE IF EXISTS hm_mcp_connections;

DROP TABLE IF EXISTS hm_mcp_tokens;

DROP TABLE IF EXISTS hm_mcp_oauth_clients;

DROP TABLE IF EXISTS hm_mcp_sessions;

DROP TABLE IF EXISTS hm_mcp_activity;

DROP TABLE IF EXISTS hm_mcp_rate_limits;

CREATE TABLE IF NOT EXISTS hm_mcp_settings (
    username VARCHAR(255) PRIMARY KEY,
    enabled INT NOT NULL DEFAULT 0,
    permissions TEXT,
    accounts TEXT,
    profile_id VARCHAR(64) NOT NULL,
    updated_at INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS hm_mcp_connections (
    id VARCHAR(64) PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    kind VARCHAR(16) NOT NULL,
    name VARCHAR(255) NOT NULL,
    client_id VARCHAR(512),
    permissions TEXT,
    accounts TEXT,
    sealed_password TEXT NOT NULL,
    sealed_cache TEXT,
    status VARCHAR(16) NOT NULL,
    created_at INT NOT NULL DEFAULT 0,
    last_used_at INT NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS hm_mcp_connections_user ON hm_mcp_connections (username);

CREATE TABLE IF NOT EXISTS hm_mcp_tokens (
    token_hash VARCHAR(64) PRIMARY KEY,
    connection_id VARCHAR(64) NOT NULL,
    kind VARCHAR(16) NOT NULL,
    wrapped_key TEXT NOT NULL,
    data TEXT,
    expires_at INT NOT NULL DEFAULT 0,
    used_at INT NOT NULL DEFAULT 0,
    created_at INT NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS hm_mcp_tokens_connection ON hm_mcp_tokens (connection_id);
CREATE INDEX IF NOT EXISTS hm_mcp_tokens_expires ON hm_mcp_tokens (expires_at);

CREATE TABLE IF NOT EXISTS hm_mcp_oauth_clients (
    client_id VARCHAR(255) PRIMARY KEY,
    kind VARCHAR(16) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    redirect_uris TEXT NOT NULL,
    metadata TEXT,
    created_at INT NOT NULL DEFAULT 0,
    last_used_at INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS hm_mcp_sessions (
    id VARCHAR(64) PRIMARY KEY,
    connection_id VARCHAR(64) NOT NULL,
    data TEXT,
    updated_at INT NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS hm_mcp_sessions_updated ON hm_mcp_sessions (updated_at);

CREATE TABLE IF NOT EXISTS hm_mcp_activity (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    connection_id VARCHAR(64),
    connection_name VARCHAR(255),
    channel VARCHAR(16) NOT NULL,
    operation VARCHAR(64) NOT NULL,
    permission VARCHAR(32),
    outcome VARCHAR(16) NOT NULL,
    summary TEXT,
    created_at INT NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS hm_mcp_activity_user ON hm_mcp_activity (username, created_at);

CREATE TABLE IF NOT EXISTS hm_mcp_rate_limits (
    rate_key VARCHAR(128) PRIMARY KEY,
    hits INT NOT NULL DEFAULT 0,
    window_start INT NOT NULL DEFAULT 0
);
