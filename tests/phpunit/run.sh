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

    cat ${SCRIPT_DIR}/data/schema_sqlite.sql | sqlite3 ${FILE}
    cat ${SCRIPT_DIR}/data/seed.sql | sqlite3 ${FILE}

elif [ "$DB" = "mysql" ]; then
    # Load schema.sql
    mysql --defaults-extra-file=.github/tests/my.cnf cypht_test < ${SCRIPT_DIR}/data/schema.sql
    
    # Load seed.sql
    mysql --defaults-extra-file=.github/tests/my.cnf cypht_test < ${SCRIPT_DIR}/data/seed_mysql.sql

elif [ "$DB" = "postgres" ]; then
    export DB_DRIVER=pgsql
    # Load schema.sql
    PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -f ${SCRIPT_DIR}/data/schema_postgres.sql
    # Load seed.sql
    PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -f ${SCRIPT_DIR}/data/seed_postgres.sql

else
    echo "Database not supported in test: ${DB}"
    exit 1
fi

phpunit --bootstrap vendor/autoload.php --configuration ${SCRIPT_DIR}/phpunit.xml --testdox $@
