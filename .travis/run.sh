#!/bin/bash

phpunit_tests() {
    cd tests/phpunit/ && /usr/local/bin/phpunit && cd ../../
}

selenium_tests() {
    cd tests/selenium/ && sh ./runall.sh && cd ../../
}

BUILD="$DB$TRAVIS_PHP_VERSION"
case "$BUILD" in
    #mysql5.5)
        #phpunit_tests && selenium_tests
    #;;
    mysql5.4)
        phpunit_tests && selenium_tests
    ;;
    sqlite5.6)
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
