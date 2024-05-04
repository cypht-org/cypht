#!/usr/bin/env sh

set -e

APP_DIR=/usr/local/share/cypht
# CYPHT_CONFIG_FILE=${APP_DIR}/hm3.ini


# TODO: validate env var values here


# Modules

# TODO: deal with modules

enable_disable_module() {
    local module=${1}
    local setting=${2}
    # For some reason, "(; )?" isn't working but ";\{0,1\} \{0,1\}" does the same thing
    if [ ${setting} = enable ]
    then
        sed -i "s/^;\{0,1\} \{0,1\}modules\[\]=${module}/modules[]=${module}/" ${CYPHT_CONFIG_FILE}
        if [ ${module} = api_login ]; then sed -i "s/;\{0,1\} \{0,1\}api_login_key=/api_login_key=/" ${CYPHT_CONFIG_FILE}; fi
    else
        sed -i "s/^;\{0,1\} \{0,1\}modules\[\]=${module}/; modules[]=${module}/" ${CYPHT_CONFIG_FILE}
        if [ ${module} = api_login ]; then sed -i "s/;\{0,1\} \{0,1\}api_login_key=/; api_login_key=/" ${CYPHT_CONFIG_FILE}; fi
    fi
}

# if [ ! -z ${CYPHT_MODULE_CORE+x} ]; then enable_disable_module core ${CYPHT_MODULE_CORE}; fi
# if [ ! -z ${CYPHT_MODULE_CONTACTS+x} ]; then enable_disable_module contacts ${CYPHT_MODULE_CONTACTS}; fi
# if [ ! -z ${CYPHT_MODULE_LOCAL_CONTACTS+x} ]; then enable_disable_module local_contacts ${CYPHT_MODULE_LOCAL_CONTACTS}; fi
# if [ ! -z ${CYPHT_MODULE_LDAP_CONTACTS+x} ]; then enable_disable_module ldap_contacts ${CYPHT_MODULE_LDAP_CONTACTS}; fi


# Wait for database to be ready then setup tables for sessions, authentication, and settings as needed
${APP_DIR}/scripts/setup_database.php

#
# Additional tasks based on the newly-configured settings
#

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
    chown www-data:www-data ${USER_SETTINGS_DIR}
fi

# Attachment Location - create directory
echo "\nCreating director for attachment location: ${ATTACHMENT_DIR}\n"
mkdir -p ${ATTACHMENT_DIR}
chown www-data:www-data ${ATTACHMENT_DIR}

# Change /var/lib/nginx owner from root to www-data to avoid "permission denied" error.
chown -R www-data:www-data /var/lib/nginx

# Application Data Location - create directory
mkdir -p ${APP_DATA_DIR}
chown www-data:www-data ${APP_DATA_DIR}

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

#
# Create user account in database (or change password if user already exists)
#

# TODO: only do this if the 3 vars are set

if [ "${USER_CONFIG_TYPE}" = "DB" ]
then
    php ./scripts/create_account.php ${AUTH_USERNAME} ${AUTH_PASSWORD}
fi
#OR maybe run the following if the user already exists...
#php ./scripts/update_password.php ${CYPHT_AUTH_USERNAME} ${CYPHT_AUTH_PASSWORD}

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
