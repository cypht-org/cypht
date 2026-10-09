DROP TABLE IF EXISTS hm_user;

DROP TABLE IF EXISTS hm_user_session;

DROP TABLE IF EXISTS hm_user_settings;

DROP TABLE IF EXISTS hm_login_attempts;

CREATE TABLE IF NOT EXISTS hm_user (username varchar(255), hash varchar(255), primary key (username));

CREATE TABLE IF NOT EXISTS hm_user_session (hm_id varchar(255), data longblob, date timestamp, lock int default 0, hm_version int default 1, primary key (hm_id));

CREATE TABLE IF NOT EXISTS hm_user_settings(username varchar(255), settings longblob, primary key (username));

CREATE TABLE IF NOT EXISTS hm_login_attempts (attempt_key varchar(255), attempt_count int default 0, locked_until int default 0, last_attempt int default 0, primary key (attempt_key));

DROP TABLE IF EXISTS hm_mcp_settings;

DROP TABLE IF EXISTS hm_mcp_connections;

DROP TABLE IF EXISTS hm_mcp_tokens;

DROP TABLE IF EXISTS hm_mcp_oauth_clients;

DROP TABLE IF EXISTS hm_mcp_sessions;

DROP TABLE IF EXISTS hm_mcp_activity;

DROP TABLE IF EXISTS hm_mcp_rate_limits;

CREATE TABLE IF NOT EXISTS hm_mcp_settings (
    username TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 0,
    permissions TEXT,
    accounts TEXT,
    profile_id TEXT NOT NULL,
    updated_at INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (username)
);

CREATE TABLE IF NOT EXISTS hm_mcp_connections (
    id TEXT NOT NULL,
    username TEXT NOT NULL,
    kind TEXT NOT NULL,
    name TEXT NOT NULL,
    client_id TEXT,
    permissions TEXT,
    accounts TEXT,
    sealed_password TEXT NOT NULL,
    sealed_cache TEXT,
    status TEXT NOT NULL,
    created_at INTEGER NOT NULL DEFAULT 0,
    last_used_at INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
);
CREATE INDEX IF NOT EXISTS hm_mcp_connections_user ON hm_mcp_connections (username);

CREATE TABLE IF NOT EXISTS hm_mcp_tokens (
    token_hash TEXT NOT NULL,
    connection_id TEXT NOT NULL,
    kind TEXT NOT NULL,
    wrapped_key TEXT NOT NULL,
    data TEXT,
    expires_at INTEGER NOT NULL DEFAULT 0,
    used_at INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (token_hash)
);
CREATE INDEX IF NOT EXISTS hm_mcp_tokens_connection ON hm_mcp_tokens (connection_id);
CREATE INDEX IF NOT EXISTS hm_mcp_tokens_expires ON hm_mcp_tokens (expires_at);

CREATE TABLE IF NOT EXISTS hm_mcp_oauth_clients (
    client_id TEXT NOT NULL,
    kind TEXT NOT NULL,
    client_name TEXT NOT NULL,
    redirect_uris TEXT NOT NULL,
    metadata TEXT,
    created_at INTEGER NOT NULL DEFAULT 0,
    last_used_at INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (client_id)
);

CREATE TABLE IF NOT EXISTS hm_mcp_sessions (
    id TEXT NOT NULL,
    connection_id TEXT NOT NULL,
    data TEXT,
    updated_at INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
);
CREATE INDEX IF NOT EXISTS hm_mcp_sessions_updated ON hm_mcp_sessions (updated_at);

CREATE TABLE IF NOT EXISTS hm_mcp_activity (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    connection_id TEXT,
    connection_name TEXT,
    channel TEXT NOT NULL,
    operation TEXT NOT NULL,
    permission TEXT,
    outcome TEXT NOT NULL,
    summary TEXT,
    created_at INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS hm_mcp_activity_user ON hm_mcp_activity (username, created_at);

CREATE TABLE IF NOT EXISTS hm_mcp_rate_limits (
    rate_key TEXT NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    window_start INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (rate_key)
);
