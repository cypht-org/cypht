#!/bin/bash

setup_ldap() {
    if [ "$TRAVIS_PHP_VERSION" = "7.0" ]; then
        echo 'extension=ldap.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini;
    fi
    if [ "$TRAVIS_PHP_VERSION" = "7.1" ]; then
        echo 'extension=ldap.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini;
    fi
}
setup_ldap
