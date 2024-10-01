#!/usr/bin/env bash

# example run: ./run.sh --filter=Hm_Test_Core_Message_Functions

set -e

SCRIPT_DIR=$(dirname $(realpath "$0"))

DB="${DB:-sqlite}"

echo "SETTING UP DB $DB"

export DB_DRIVER=$DB
export DB_NAME=cypht_test

if [ "$DB" = "sqlite" ]; then

    FILE=/tmp/test.db

    export DB_CONNECTION_TYPE=socket
    export DB_SOCKET=${FILE}

    cat ${SCRIPT_DIR}/data/schema.sql | sqlite3 ${FILE}
    cat ${SCRIPT_DIR}/data/seed.sql | sqlite3 ${FILE}

# elif [ "$DB" = "mysql" ]; then    # TODO

# elif [ "$DB" = "pgsql" ]; then    # TODO

else
    echo "Database not supported in test: ${DB}"
    exit 1
fi

phpunit --configuration ${SCRIPT_DIR}/phpunit.xml $@
