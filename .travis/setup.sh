#!/bin/bash

# Travis Setup
# ------------
# This script configures everything needed in a Travis instance
# to run the Cypht phpunit tests and Selenium tests.


# Add repos
update_repos() {
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt-get -qq update
}

# PHP 7+ needs to have the LDAP extension manually enabled
setup_ldap() {
    if [ "$TRAVIS_PHP_VERSION" = "7.0" ]; then
        echo 'extension=ldap.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
    fi
    if [ "$TRAVIS_PHP_VERSION" = "7.1" ]; then
        echo 'extension=ldap.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
    fi
    ls -lR /home/travis/.phpenv/versions/7.1.5/lib/php/extensions/
}

# Add a system user dovecot will use for authentication
setup_user() {
    sudo useradd -m -d /home/testuser -p '$1$BMvnSsOY$DXbm292ZTfTwuEwUpu/Lo/' testuser
}

# Install Dovecot
install_dovecot() {
    sudo sh .travis/dovecot.sh
}

# Select the browser and driver config for Selenium tests
selenium_config() {
    if [ "$TRAVIS_PHP_VERSION" = "5.4" ]; then
        mv .travis/creds.py-chrome creds.py
    fi
    if [ "$TRAVIS_PHP_VERSION" = "5.5" ]; then
        mv .travis/creds.py-chrome creds.py
    fi
    if [ "$TRAVIS_PHP_VERSION" = "5.6" ]; then
        mv .travis/creds.py-edge creds.py
    fi
    if [ "$TRAVIS_PHP_VERSION" = "7.0" ]; then
        mv .travis/creds.py-ie creds.py
    fi
    if [ "$TRAVIS_PHP_VERSION" = "7.1" ]; then
        mv .travis/creds.py-safari creds.py
    fi
}

# Configure Cypht
setup_cypht() {
    mv .travis/hm3.ini .
    if [ "$DB" = "postgresql" ]; then
        sed -i 's/db_driver=mysql/db_driver=pgsql/' hm3.ini
        sed -i 's/mysql/pgsql/' tests/phpunit/mocks.php
    fi
    if [ "$DB" = "sqlite" ]; then
        sed -i 's/db_driver=mysql/db_driver=sqlite/' hm3.ini
        sed -i 's/mysql/sqlite/' tests/phpunit/mocks.php
        sed -i "s/'host'/'socket'/" tests/phpunit/mocks.php
    fi
    mv creds.py tests/selenium/
    php ./scripts/config_gen.php
}

# Install a version of phpunit that is compatible with the version of PHP that is installed
install_phpunit() {
    if [ "$TRAVIS_PHP_VERSION" = "5.4" ]; then
        wget https://phar.phpunit.de/phpunit-4.8.phar -O phpunit
    fi
    if [ "$TRAVIS_PHP_VERSION" = "5.5" ]; then
        wget https://phar.phpunit.de/phpunit-4.8.phar -O phpunit
    fi
    if [ "$TRAVIS_PHP_VERSION" = "5.6" ]; then
        wget https://phar.phpunit.de/phpunit-5.7.phar -O phpunit
    fi
    if [ "$TRAVIS_PHP_VERSION" = "7.0" ]; then
        wget https://phar.phpunit.de/phpunit-5.7.phar -O phpunit
    fi
    if [ "$TRAVIS_PHP_VERSION" = "7.1" ]; then
        wget https://phar.phpunit.de/phpunit-5.7.phar -O phpunit
    fi
    chmod +x phpunit
    sudo mv phpunit /usr/local/bin/phpunit
}

# install selenium
install_selenium() {
    sudo apt-get install python-pip
    sudo pip install selenium
}

# install and configure Apache and PHP-FPM
install_apache() {
    sudo apt-get install apache2 libapache2-mod-fastcgi
    sudo cp ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf.default ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf
    sudo a2enmod rewrite actions fastcgi alias
    echo "cgi.fix_pathinfo = 1" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
    if [ "$TRAVIS_PHP_VERSION" = "7.0" ]; then
        sudo cp .travis/www.conf ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.d/
    fi
    if [ "$TRAVIS_PHP_VERSION" = "7.1" ]; then
        sudo cp .travis/www.conf ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.d/
    fi

    ~/.phpenv/versions/$(phpenv version-name)/sbin/php-fpm
    sudo rm -f /etc/apache2/sites-enabled/000-default.conf
    sudo rm -f /etc/apache2/sites-available/000-default.conf
    sudo cp -f .travis/travis-ci-apache /etc/apache2/sites-available/000-default.conf
    sudo ln -s /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/000-default.conf
    sudo sed -e "s?%TRAVIS_BUILD_DIR%?$(pwd)?g" --in-place /etc/apache2/sites-available/000-default.conf
    sudo chmod +x /home/travis
    sudo chmod +x /home/travis/build
    sudo chmod +x /home/travis/build/site
    sudo service apache2 restart
}

# Setup base data needed by the phpunit tests
bootstrap_unit_tests() {
    setup_db
    echo '+2IdQejfHu4FNYOA3tm0DJVQNg92gcpJf8ETeVj+HK0OU6J5iaV/J823rLm8+5Et7tQLoCCoZwElGTH7N2P2M4JMct1jRyWgjqJQn9FYlovFYj/8fLwkixGo+VMNIKsUwJ42GXTj61nn0Rf4+FO688SfAR5LhaLTXlR6XZ9mJD2/2RX1X+Z1JVl7SrqELgE8wnz5IYCrzqBbgK4MDn86rTtPM9jie3gFS9viMZ7OQRENbXLvwBaIXNLvQlZZn2JBdzXF1spoLnSlq8P0pYXlDig==' > tests/phpunit/data/testuser.txt
}

# Create and populate database for phpunit tests
setup_db() {
    echo "SETTING UP DB $DB"
    if [ "$DB" = "postgresql" ]; then
        psql -c 'create database test;' -U postgres
        psql -c 'CREATE TABLE hm_user (username varchar(255) primary key not null, hash varchar(255));' -U postgres test
        psql -c 'CREATE TABLE hm_user_session (hm_id varchar(250) primary key not null, data text, date timestamp);' -U postgres test
        psql -c 'CREATE TABLE hm_user_settings (username varchar(250) primary key not null, settings text);' -U postgres test
        psql -c "CREATE USER test with password '123456';" -U postgres test
        psql -c 'GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA PUBLIC TO test;' -U postgres test
        psql -c "insert into hm_user values('unittestuser', 'sha512:86000:xfEgf7NIUQ2XkeU5tnIcA+HsN8pUllMVdzpJxCSwmbsZAE8Hze3Zs+MeIqepwocYteJ92vhq7pjOfrVThg/p1voELkDdPenU8i2PgG9UTI0IJTGhMN7rsUILgT6XlMAKLp/u2OD13sukUFcQNTdZNFqMsuTVTYw/Me2tAnFwgO4=:rfyUhYsWBCknx6EmbeswN0fy0hAC0N3puXzwWyDRquA=');" -U postgres test
        psql -c "insert into hm_user_settings values('testuser', 'sFpVPU/hPvmfeiEKUBs4w1EizmbW/Ze2BALZf6kdJrIU3KVZrsqIhKaWTNNFRm3p51ssRAH2mpbxBMhsdpOAqIZMXFHjLttRu9t5WZWOkN7qwEh2LRq6imbkMkfqXg//K294QDLyWjE0Lsc/HSGqnguBF0YUVLVmWmdeqq7/OrXUo4HNbU88i4s2gkukKobJA2hjcOEq/rLOXr3t4LnLlcISnUbt4ptalSbeRrOnx4ehZV8hweQf1E+ID7s/a+8HHx1Qo713JDzReoLEKUsxRQ==');" -U postgres test
    fi
    if [ "$DB" = "sqlite" ]; then
        touch /tmp/test.db
        sqlite3 /tmp/test.db 'create table hm_user (username varchar(255), hash varchar(255), primary key (username));'
        sqlite3 /tmp/test.db 'create table hm_user_session (hm_id varchar(255), data longblob, date timestamp, primary key (hm_id));'
        sqlite3 /tmp/test.db 'create table hm_user_settings( username varchar(255), settings longblob, primary key (username));'
        sqlite3 /tmp/test.db "insert into hm_user values('unittestuser', 'sha512:86000:xfEgf7NIUQ2XkeU5tnIcA+HsN8pUllMVdzpJxCSwmbsZAE8Hze3Zs+MeIqepwocYteJ92vhq7pjOfrVThg/p1voELkDdPenU8i2PgG9UTI0IJTGhMN7rsUILgT6XlMAKLp/u2OD13sukUFcQNTdZNFqMsuTVTYw/Me2tAnFwgO4=:rfyUhYsWBCknx6EmbeswN0fy0hAC0N3puXzwWyDRquA=');"
        sqlite3 /tmp/test.db "insert into hm_user_settings values('testuser', 'sFpVPU/hPvmfeiEKUBs4w1EizmbW/Ze2BALZf6kdJrIU3KVZrsqIhKaWTNNFRm3p51ssRAH2mpbxBMhsdpOAqIZMXFHjLttRu9t5WZWOkN7qwEh2LRq6imbkMkfqXg//K294QDLyWjE0Lsc/HSGqnguBF0YUVLVmWmdeqq7/OrXUo4HNbU88i4s2gkukKobJA2hjcOEq/rLOXr3t4LnLlcISnUbt4ptalSbeRrOnx4ehZV8hweQf1E+ID7s/a+8HHx1Qo713JDzReoLEKUsxRQ==');"
    else
        mysql -u root -e 'create database if not exists test;'
        mysql -u root -e 'create table hm_user (username varchar(255), hash varchar(255), primary key (username));' test
        mysql -u root -e 'create table hm_user_session (hm_id varchar(255), data longblob, date timestamp, primary key (hm_id));' test
        mysql -u root -e 'create table hm_user_settings( username varchar(255), settings longblob, primary key (username));' test
        mysql -u root -e "create user 'test'@'localhost' identified by '123456';"
        mysql -u root -e "grant all privileges on test.* to 'test'@'localhost';"
        mysql -u root -e "insert into hm_user values('unittestuser', 'sha512:86000:xfEgf7NIUQ2XkeU5tnIcA+HsN8pUllMVdzpJxCSwmbsZAE8Hze3Zs+MeIqepwocYteJ92vhq7pjOfrVThg/p1voELkDdPenU8i2PgG9UTI0IJTGhMN7rsUILgT6XlMAKLp/u2OD13sukUFcQNTdZNFqMsuTVTYw/Me2tAnFwgO4=:rfyUhYsWBCknx6EmbeswN0fy0hAC0N3puXzwWyDRquA=');" test
        mysql -u root -e "insert into hm_user_settings values('testuser', 'sFpVPU/hPvmfeiEKUBs4w1EizmbW/Ze2BALZf6kdJrIU3KVZrsqIhKaWTNNFRm3p51ssRAH2mpbxBMhsdpOAqIZMXFHjLttRu9t5WZWOkN7qwEh2LRq6imbkMkfqXg//K294QDLyWjE0Lsc/HSGqnguBF0YUVLVmWmdeqq7/OrXUo4HNbU88i4s2gkukKobJA2hjcOEq/rLOXr3t4LnLlcISnUbt4ptalSbeRrOnx4ehZV8hweQf1E+ID7s/a+8HHx1Qo713JDzReoLEKUsxRQ==');" test
    fi
}

# install coveralls
install_coveralls() {
    wget -c -nc --retry-connrefused --tries=0 https://github.com/satooshi/php-coveralls/releases/download/v1.0.1/coveralls.phar
    chmod +x coveralls.phar
    php coveralls.phar --version
}

# install libsodium
install_sodium() {
    sudo apt-get install libsodium-dev -y
    pecl install libsodium
}

update_repos
setup_ldap
setup_user
install_dovecot
selenium_config
setup_cypht
install_phpunit
install_coveralls
install_selenium
install_apache
install_sodium
bootstrap_unit_tests
