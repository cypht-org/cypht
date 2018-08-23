#!/bin/bash

phpunit_tests() {
    cd tests/phpunit/ && /usr/local/bin/phpunit && cd ../../
}

selenium_tests() {
    cd tests/selenium/ && sh ./runall.sh && cd ../../
}

BUILD="$DB$TRAVIS_PHP_VERSION"
case "$BUILD" in
    postgresql5.6)
        phpunit_tests && selenium_tests
    ;;
    postgresql7.0)
        phpunit_tests && selenium_tests
    ;;
    postgresql7.1)
        phpunit_tests && selenium_tests
    ;;
    postgresql7.2)
        phpunit_tests && selenium_tests
    ;;
    *)
        phpunit_tests
    ;;
esac
