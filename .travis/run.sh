#!/bin/bash

phpunit_tests() {
    cd tests/phpunit/ && /usr/local/bin/phpunit && cd ../../
}

selenium_tests() {
    cd tests/selenium/ && sh ./runall.sh && cd ../../
}

BUILD="$DB$TRAVIS_PHP_VERSION"
case "$BUILD" in
    mysql5.4)
        phpunit_tests && selenium_tests
    ;;
    postgresql5.4)
        phpunit_tests && selenium_tests
    ;;
    postgresqlnightly)
        phpunit_tests && selenium_tests
    ;;
    mysqlnightly)
        phpunit_tests && selenium_tests
    ;;
    *)
        phpunit_tests
    ;;
esac
