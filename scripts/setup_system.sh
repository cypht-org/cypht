#!/usr/bin/env sh

# This script is for creating directories and generating the config

set -e

SCRIPT_DIR=$(dirname $(realpath "$0"))

# TODO: source these defaults from an .env file or some other place?
USER_CONFIG_TYPE="${USER_CONFIG_TYPE:-file}"
USER_SETTINGS_DIR="${USER_SETTINGS_DIR:-/var/lib/hm3/users}"
ATTACHMENT_DIR="${ATTACHMENT_DIR:-/var/lib/hm3/attachments}"
APP_DATA_DIR="${APP_DATA_DIR:-/var/lib/hm3/app_data}"


if [ "${USER_CONFIG_TYPE}" = "file" ]
then
    echo "Creating directory for settings ${USER_SETTINGS_DIR}"
    mkdir -p ${USER_SETTINGS_DIR}
fi

echo "Creating directory for attachments ${ATTACHMENT_DIR}"
mkdir -p ${ATTACHMENT_DIR}

echo "Creating directory for application data ${APP_DATA_DIR}"
mkdir -p ${APP_DATA_DIR}

# TODO: should a user be created if USER_CONFIG_TYPE=file  ?
if [[ "${USER_CONFIG_TYPE}" = "DB" && -n "${AUTH_USERNAME}" ]]
then
    php ${SCRIPT_DIR}/../scripts/create_account.php ${AUTH_USERNAME} ${AUTH_PASSWORD}
fi

# Generate the run-time configuration
php ${SCRIPT_DIR}/../scripts/config_gen.php
