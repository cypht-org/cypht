#!/usr/bin/env sh

set -e

APP_DIR=/usr/local/share/cypht
cd ${APP_DIR}

# TODO: validate env var values here, perhaps in php or in Hm_Site_Config_File()

# TODO: source these defaults from an .env file or some other place?
USER_CONFIG_TYPE="${USER_CONFIG_TYPE:-file}"
USER_SETTINGS_DIR="${USER_SETTINGS_DIR:-/var/lib/hm3/users}"
ATTACHMENT_DIR="${ATTACHMENT_DIR:-/var/lib/hm3/attachments}"

# Wait for database to be ready then setup tables
./scripts/setup_database.php

# Setup filesystem and users
./scripts/setup_system.sh

# Enable the program in the web-server

if [ "${USER_CONFIG_TYPE}" = "file" ]
then
    chown www-data:www-data ${USER_SETTINGS_DIR}
fi

chown www-data:www-data ${ATTACHMENT_DIR}
chown -R www-data:www-data /var/lib/nginx

# When LOG_FILE is set, ensure its directory exists and is writable
if [ -n "${LOG_FILE}" ]; then
    case "${LOG_FILE}" in
        /*) LOG_PATH="${LOG_FILE}" ;;
        *)  LOG_PATH="${APP_DIR}/${LOG_FILE}" ;;
    esac
    LOG_DIR=$(dirname "${LOG_PATH}")
    mkdir -p "${LOG_DIR}"
    chown www-data:www-data "${LOG_DIR}"
    touch "${LOG_PATH}"
    chown www-data:www-data "${LOG_PATH}"
fi

rm -r /var/www
ln -s $(pwd)/site /var/www

# Start services
exec /usr/bin/supervisord -c /etc/supervisord.conf
