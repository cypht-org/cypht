#!/bin/bash

phpunit_tests() {
    phpunit --bootstrap vendor/autoload.php --configuration tests/phpunit/phpunit.xml --testdox
}

selenium_tests() {
    cd tests/selenium/ && sh ./runall.sh && cd ../../
}

# Main
echo "database: ${DB}"
echo "php-version: ${PHP_V}"
echo "test-arg: ${TEST_ARG}"

ARG="${TEST_ARG}"
case "$ARG" in
    phpunit)
        phpunit_tests
    ;;
    ui)
        selenium_tests
    ;;
    *)
        phpunit_tests
    ;;
esac
