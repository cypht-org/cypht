#!/usr/bin/env bash
# MariaDB/MySQL initialization script for Cypht
# This script runs in /docker-entrypoint-initdb.d/ and ensures the database user
# has proper permissions for Docker networking.
#
# It is intended to be used **only** with MariaDB/MySQL.
# - It creates the application user with '@%' host for Docker network connections
# - It also ensures a '@localhost' user exists for local access / healthchecks
#
# Note: PostgreSQL and SQLite do not use this script and do **not** have the same
# Docker networking permission problem:
# - SQLite is file-based, has no users, and is accessed directly by the app
# - PostgreSQL's official image already creates the user/database from POSTGRES_*
#   env vars and its default pg_hba.conf allows password auth from other containers

set -e

# MySQL/MariaDB initialization
MYSQL_USER="${MYSQL_USER:-cypht}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-cypht_password}"
MYSQL_DATABASE="${MYSQL_DATABASE:-cypht}"

# Get root password - MariaDB init scripts can access this via environment or file
if [ -n "$MYSQL_ROOT_PASSWORD_FILE" ] && [ -f "$MYSQL_ROOT_PASSWORD_FILE" ]; then
    MYSQL_ROOT_PASSWORD=$(cat "$MYSQL_ROOT_PASSWORD_FILE")
elif [ -n "$MYSQL_ROOT_PASSWORD" ]; then
    MYSQL_ROOT_PASSWORD="$MYSQL_ROOT_PASSWORD"
else
    echo "Error: MYSQL_ROOT_PASSWORD not set" >&2
    exit 1
fi

# Create user with wildcard host (%) to allow connections from any Docker container
# This is necessary because Docker containers connect via service names, not localhost.
# Note: MYSQL_USER environment variable creates 'user'@'localhost', but we also need 'user'@'%'.
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<EOF_SQL
-- Create user with wildcard host for Docker network connections
-- This allows connections from any container in the Docker network
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_USER}'@'%';

-- Ensure localhost user exists (may already exist from MYSQL_USER env var)
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'localhost' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_USER}'@'localhost';

-- Flush privileges to ensure changes take effect
FLUSH PRIVILEGES;
EOF_SQL

echo "✓ Created MySQL/MariaDB user '${MYSQL_USER}' with Docker network permissions (@'%' and @'localhost')"

