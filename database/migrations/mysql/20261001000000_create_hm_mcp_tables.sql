CREATE TABLE IF NOT EXISTS hm_mcp_settings (
    username VARCHAR(255) NOT NULL,
    enabled INT NOT NULL DEFAULT 0,
    permissions TEXT,
    accounts TEXT,
    profile_id VARCHAR(64) NOT NULL,
    updated_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (username)
);

CREATE TABLE IF NOT EXISTS hm_mcp_connections (
    id VARCHAR(64) NOT NULL,
    username VARCHAR(255) NOT NULL,
    kind VARCHAR(16) NOT NULL,
    name VARCHAR(255) NOT NULL,
    client_id VARCHAR(512),
    permissions TEXT,
    accounts TEXT,
    sealed_password TEXT NOT NULL,
    sealed_cache MEDIUMTEXT,
    status VARCHAR(16) NOT NULL,
    created_at INT NOT NULL DEFAULT 0,
    last_used_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX hm_mcp_connections_user (username)
);

CREATE TABLE IF NOT EXISTS hm_mcp_tokens (
    token_hash VARCHAR(64) NOT NULL,
    connection_id VARCHAR(64) NOT NULL,
    kind VARCHAR(16) NOT NULL,
    wrapped_key TEXT NOT NULL,
    data TEXT,
    expires_at INT NOT NULL DEFAULT 0,
    used_at INT NOT NULL DEFAULT 0,
    created_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (token_hash),
    INDEX hm_mcp_tokens_connection (connection_id),
    INDEX hm_mcp_tokens_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS hm_mcp_oauth_clients (
    client_id VARCHAR(255) NOT NULL,
    kind VARCHAR(16) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    redirect_uris TEXT NOT NULL,
    metadata TEXT,
    created_at INT NOT NULL DEFAULT 0,
    last_used_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (client_id)
);

CREATE TABLE IF NOT EXISTS hm_mcp_sessions (
    id VARCHAR(64) NOT NULL,
    connection_id VARCHAR(64) NOT NULL,
    data MEDIUMTEXT,
    updated_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX hm_mcp_sessions_updated (updated_at)
);

CREATE TABLE IF NOT EXISTS hm_mcp_activity (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    connection_id VARCHAR(64),
    connection_name VARCHAR(255),
    channel VARCHAR(16) NOT NULL,
    operation VARCHAR(64) NOT NULL,
    permission VARCHAR(32),
    outcome VARCHAR(16) NOT NULL,
    summary TEXT,
    created_at INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX hm_mcp_activity_user (username, created_at)
);

CREATE TABLE IF NOT EXISTS hm_mcp_rate_limits (
    rate_key VARCHAR(128) NOT NULL,
    hits INT NOT NULL DEFAULT 0,
    window_start INT NOT NULL DEFAULT 0,
    PRIMARY KEY (rate_key)
);
