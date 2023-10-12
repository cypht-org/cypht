#!/bin/bash

phpunit_tests() {
    phpunit --configuration tests/phpunit/phpunit.xml --testdox
}

selenium_tests() {
    cd tests/selenium/ && bash ./runall.sh && cd ../../
}

# Main
echo "database: ${DB}"
echo "php-version: ${PHP_V}"
echo "test-arg: ${TEST_ARG}"

case "${TEST_ARG}" in
    phpunit)
        phpunit_tests
    ;;
    selenium)
        selenium_tests
    ;;
    *)
        phpunit_tests
    ;;
esac
