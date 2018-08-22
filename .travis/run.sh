#!/bin/bash

phpunit_tests() {
    cd tests/phpunit/ && /usr/local/bin/phpunit && cd ../../
}

selenium_tests() {
    cd tests/selenium/ && sh ./runall.sh && cd ../../
}

phpunit_tests && selenium_tests
