#!/usr/bin/env sh

set -e

APP_DIR=/usr/local/share/cypht


# TODO: validate env var values here, perhaps in php



# Wait for database to be ready then setup tables for sessions, authentication, and settings as needed
${APP_DIR}/scripts/setup_database.php

${APP_DIR}/scripts/setup_system.sh

#
# Additional tasks based on the newly-configured settings
#

# TODO: source these defaults from an .env file or some other place?
USER_CONFIG_TYPE="${USER_CONFIG_TYPE:-file}"
USER_SETTINGS_DIR="${USER_SETTINGS_DIR:-/var/lib/hm3/users}"
ATTACHMENT_DIR="${ATTACHMENT_DIR:-/var/lib/hm3/attachments}"
APP_DATA_DIR="${APP_DATA_DIR:-/var/lib/hm3/app_data}"


if [ "${USER_CONFIG_TYPE}" = "file" ]
then
    chown www-data:www-data ${USER_SETTINGS_DIR}
fi

chown www-data:www-data ${ATTACHMENT_DIR}
chown -R www-data:www-data /var/lib/nginx
chown www-data:www-data ${APP_DATA_DIR}


# Generate the run-time configuration
#
cd $APP_DIR
php ./scripts/config_gen.php

#
# Enable the program in the web-server
#
rm -r /var/www
ln -s ${APP_DIR}/site /var/www


# TODO: should a user be created if USER_CONFIG_TYPE=file  ?

if [[ "${USER_CONFIG_TYPE}" = "DB" && -n "${AUTH_USERNAME}" ]]
then
    php ./scripts/create_account.php ${AUTH_USERNAME} ${AUTH_PASSWORD}
fi


#
# Close out tasks
#

# now that we're definitely done writing configuration, let's clear out the relevant environment variables (so that stray "phpinfo()" calls don't leak secrets from our code)
#for e in "${envs[@]}"; do
#    unset "$e"
#done

# Start supervisord and services
/usr/bin/supervisord -c /etc/supervisord.conf

exec "$@"
