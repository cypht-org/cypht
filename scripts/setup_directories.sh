#!/usr/bin/env sh

set -e


# TODO: source these defaults from an .env file or some other place?
USER_CONFIG_TYPE="${USER_CONFIG_TYPE:-file}"
USER_SETTINGS_DIR="${USER_SETTINGS_DIR:-/var/lib/hm3/users}"
ATTACHMENT_DIR="${ATTACHMENT_DIR:-/var/lib/hm3/attachments}"
APP_DATA_DIR="${APP_DATA_DIR:-/var/lib/hm3/app_data}"
# AUTH_USERNAME="${AUTH_USERNAME:-admin}"
# AUTH_PASSWORD="${AUTH_PASSWORD:-admin}"


# Settings Location - create directory if config type is "file"
if [ "${USER_CONFIG_TYPE}" = "file" ]
then
    mkdir -p ${USER_SETTINGS_DIR}
    # chown www-data:www-data ${USER_SETTINGS_DIR}
fi

# Attachment Location - create directory
echo "\nCreating director for attachment location: ${ATTACHMENT_DIR}\n"
mkdir -p ${ATTACHMENT_DIR}
# chown www-data:www-data ${ATTACHMENT_DIR}

# Change /var/lib/nginx owner from root to www-data to avoid "permission denied" error.
# chown -R www-data:www-data /var/lib/nginx

# Application Data Location - create directory
mkdir -p ${APP_DATA_DIR}
# chown www-data:www-data ${APP_DATA_DIR}

#
# Generate the run-time configuration
#
cd $APP_DIR
php ./scripts/config_gen.php

#
# Enable the program in the web-server
#
rm -r /var/www
ln -s ${APP_DIR}/site /var/www
