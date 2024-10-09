#!/bin/bash

STATUS_TITLE() {
	echo -ne "\033[0;34m${1}: \033[0m"
}

STATUS_DONE() {
	echo -e "\033[0;33mDone √\033[0m"
}

STATUS_ERROR() {
	echo -e "\033[1;31mError ×\033[0m"
}

# Configure Cypht
setup_cypht() {
    cp .github/tests/.env .
    if [ "$DB" = "postgres" ]; then
        # .env
        sed -i 's/DB_DRIVER=mysql/DB_DRIVER=pgsql/' .env
        # mocks.php
        sed -i 's/mysql/pgsql/' tests/phpunit/mocks.php
    fi
    if [ "$DB" = "sqlite" ]; then
        # .env
        sed -i 's/DB_DRIVER=mysql/DB_DRIVER=sqlite/' .env
        # mocks.php
        sed -i 's/mysql/sqlite/' tests/phpunit/mocks.php
        sed -i "s/'host'/'socket'/" tests/phpunit/mocks.php
    fi

    sed -i "s|ATTACHMENT_DIR=.*|ATTACHMENT_DIR=$(pwd)/hm3/attachments|" .env
    php scripts/config_gen.php
}

# Create and populate database for phpunit tests
setup_db() {
    echo "SETTING UP DB $DB"
    if [ "$DB" = "postgres" ]; then
        PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -c 'CREATE TABLE hm_user (username varchar(255) primary key not null, hash varchar(255));'
        PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -c 'CREATE TABLE hm_user_session (hm_id varchar(250) primary key not null, data text, date timestamp);'
        PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -c 'CREATE TABLE hm_user_settings (username varchar(250) primary key not null, settings text);'
        PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -c "insert into hm_user values('unittestuser', 'sha512:86000:xfEgf7NIUQ2XkeU5tnIcA+HsN8pUllMVdzpJxCSwmbsZAE8Hze3Zs+MeIqepwocYteJ92vhq7pjOfrVThg/p1voELkDdPenU8i2PgG9UTI0IJTGhMN7rsUILgT6XlMAKLp/u2OD13sukUFcQNTdZNFqMsuTVTYw/Me2tAnFwgO4=:rfyUhYsWBCknx6EmbeswN0fy0hAC0N3puXzwWyDRquA=');"
        PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -c "insert into hm_user values('testuser', '\$argon2id\$v=19\$m=65536,t=2,p=1\$dw4pTU24zRKHCEkLcloU/A\$9NJm6ALQhVpB2HTHmVHjOai912VhURUDAPsut5lrEa0');"
        PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -c "insert into hm_user_settings values('testuser', 'sFpVPU/hPvmfeiEKUBs4w1EizmbW/Ze2BALZf6kdJrIU3KVZrsqIhKaWTNNFRm3p51ssRAH2mpbxBMhsdpOAqIZMXFHjLttRu9t5WZWOkN7qwEh2LRq6imbkMkfqXg//K294QDLyWjE0Lsc/HSGqnguBF0YUVLVmWmdeqq7/OrXUo4HNbU88i4s2gkukKobJA2hjcOEq/rLOXr3t4LnLlcISnUbt4ptalSbeRrOnx4ehZV8hweQf1E+ID7s/a+8HHx1Qo713JDzReoLEKUsxRQ==');"
    fi
    if [ "$DB" = "mysql" ]; then
        mysql --defaults-extra-file=.github/tests/my.cnf -e 'create table hm_user (username varchar(255), hash varchar(255), primary key (username));' cypht_test
        mysql --defaults-extra-file=.github/tests/my.cnf -e 'create table hm_user_session (hm_id varchar(255), data longblob, date timestamp, primary key (hm_id));' cypht_test
        mysql --defaults-extra-file=.github/tests/my.cnf -e 'create table hm_user_settings(username varchar(255), settings longblob, primary key (username));' cypht_test
        mysql --defaults-extra-file=.github/tests/my.cnf -e "insert into hm_user values('unittestuser', 'sha512:86000:xfEgf7NIUQ2XkeU5tnIcA+HsN8pUllMVdzpJxCSwmbsZAE8Hze3Zs+MeIqepwocYteJ92vhq7pjOfrVThg/p1voELkDdPenU8i2PgG9UTI0IJTGhMN7rsUILgT6XlMAKLp/u2OD13sukUFcQNTdZNFqMsuTVTYw/Me2tAnFwgO4=:rfyUhYsWBCknx6EmbeswN0fy0hAC0N3puXzwWyDRquA=');" cypht_test
        mysql --defaults-extra-file=.github/tests/my.cnf -e "insert into hm_user values('testuser', '\$argon2id\$v=19\$m=65536,t=2,p=1\$dw4pTU24zRKHCEkLcloU/A\$9NJm6ALQhVpB2HTHmVHjOai912VhURUDAPsut5lrEa0');" cypht_test
        mysql --defaults-extra-file=.github/tests/my.cnf -e "insert into hm_user_settings values('testuser', 'sFpVPU/hPvmfeiEKUBs4w1EizmbW/Ze2BALZf6kdJrIU3KVZrsqIhKaWTNNFRm3p51ssRAH2mpbxBMhsdpOAqIZMXFHjLttRu9t5WZWOkN7qwEh2LRq6imbkMkfqXg//K294QDLyWjE0Lsc/HSGqnguBF0YUVLVmWmdeqq7/OrXUo4HNbU88i4s2gkukKobJA2hjcOEq/rLOXr3t4LnLlcISnUbt4ptalSbeRrOnx4ehZV8hweQf1E+ID7s/a+8HHx1Qo713JDzReoLEKUsxRQ==');" cypht_test
    fi
    if [ "$DB" = "sqlite" ]; then
        touch /tmp/test.db
        sqlite3 /tmp/test.db 'create table hm_user (username varchar(255), hash varchar(255), primary key (username));'
        sqlite3 /tmp/test.db 'create table hm_user_session (hm_id varchar(255), data longblob, date timestamp, primary key (hm_id));'
        sqlite3 /tmp/test.db 'create table hm_user_settings(username varchar(255), settings longblob, primary key (username));'
        sqlite3 /tmp/test.db "insert into hm_user values('unittestuser', 'sha512:86000:xfEgf7NIUQ2XkeU5tnIcA+HsN8pUllMVdzpJxCSwmbsZAE8Hze3Zs+MeIqepwocYteJ92vhq7pjOfrVThg/p1voELkDdPenU8i2PgG9UTI0IJTGhMN7rsUILgT6XlMAKLp/u2OD13sukUFcQNTdZNFqMsuTVTYw/Me2tAnFwgO4=:rfyUhYsWBCknx6EmbeswN0fy0hAC0N3puXzwWyDRquA=');"
        sqlite3 /tmp/test.db "insert into hm_user values('testuser', '\$argon2id\$v=19\$m=65536,t=2,p=1\$dw4pTU24zRKHCEkLcloU/A\$9NJm6ALQhVpB2HTHmVHjOai912VhURUDAPsut5lrEa0');"
        sqlite3 /tmp/test.db "insert into hm_user_settings values('testuser', 'sFpVPU/hPvmfeiEKUBs4w1EizmbW/Ze2BALZf6kdJrIU3KVZrsqIhKaWTNNFRm3p51ssRAH2mpbxBMhsdpOAqIZMXFHjLttRu9t5WZWOkN7qwEh2LRq6imbkMkfqXg//K294QDLyWjE0Lsc/HSGqnguBF0YUVLVmWmdeqq7/OrXUo4HNbU88i4s2gkukKobJA2hjcOEq/rLOXr3t4LnLlcISnUbt4ptalSbeRrOnx4ehZV8hweQf1E+ID7s/a+8HHx1Qo713JDzReoLEKUsxRQ==');"
    fi
}

# Setup base data needed by the phpunit tests
bootstrap_unit_tests() {
    setup_db
    echo '+2IdQejfHu4FNYOA3tm0DJVQNg92gcpJf8ETeVj+HK0OU6J5iaV/J823rLm8+5Et7tQLoCCoZwElGTH7N2P2M4JMct1jRyWgjqJQn9FYlovFYj/8fLwkixGo+VMNIKsUwJ42GXTj61nn0Rf4+FO688SfAR5LhaLTXlR6XZ9mJD2/2RX1X+Z1JVl7SrqELgE8wnz5IYCrzqBbgK4MDn86rTtPM9jie3gFS9viMZ7OQRENbXLvwBaIXNLvQlZZn2JBdzXF1spoLnSlq8P0pYXlDig==' > tests/phpunit/data/testuser.txt
}

# output some system info
sys_info() {
    df -h
    sudo netstat -lntp
}

##### UI START #####
# Add a system user dovecot will use for authentication
setup_user() {
	STATUS_TITLE "Setup MailUser"
	sudo useradd -m -p '$1$BMvnSsOY$DXbm292ZTfTwuEwUpu/Lo/' testuser
	sudo mkdir -p /home/testuser/mail/.imap/INBOX
	sudo chown -R testuser:testuser /home/testuser
	sudo usermod -aG mail testuser
	sudo usermod -aG postdrop testuser
	STATUS_DONE
}

# config Dovecot
setup_dovecot() {
	STATUS_TITLE "Setup Dovecot"
	sudo bash .github/tests/scripts/dovecot.sh
	if [ "$(sudo systemctl is-active dovecot.service)" == "active" ]; then
		STATUS_DONE
	else
		STATUS_ERROR
		exit 1
	fi
}

# config postfix
setup_postfix() {
	STATUS_TITLE "Setup Postfix"
	sudo bash .github/tests/scripts/postfix.sh
	if [ "$(sudo systemctl is-active postfix.service)" == "active" ]; then
		STATUS_DONE
	else
		STATUS_ERROR
		exit 1
	fi
}

#config site
setup_site() {
	STATUS_TITLE "Setup php${PHP_V}-fpm"
	sudo systemctl start php"${PHP_V}"-fpm.service
	if [ "$(sudo systemctl is-active php"${PHP_V}"-fpm.service)" == "active" ]; then
		STATUS_DONE
	else
		STATUS_ERROR
		exit 1
	fi
	STATUS_TITLE "Setup Nginx"
	sudo systemctl stop nginx.service
	sudo cp .github/tests/selenium/nginx/nginx-site.conf /etc/nginx/sites-available/default
	sudo mkdir /etc/nginx/nginxconfig
	sudo cp .github/tests/selenium/nginx/php_fastcgi.conf /etc/nginx/nginxconfig/php_fastcgi.conf
	sudo sed -e "s?%VERSION%?${PHP_V}?g" --in-place /etc/nginx/sites-available/default
	sudo ln -sf "$(pwd)" /var/www/cypht
	sudo systemctl start nginx
	if [ "$(curl -s -o /dev/null -w '%{http_code}' 'http://cypht-test.org')" -eq 200 ]; then
		STATUS_DONE
	else
		STATUS_ERROR
		exit 1
	fi
    STATUS_TITLE "Setup required Directories"
    mkdir -p "$(pwd)/hm3/attachments"
	STATUS_DONE
}

##### UI END #####

# setup just what is needed for the phpunit unit tests
setup_unit_tests() {
    setup_cypht
    bootstrap_unit_tests
}

setup_ui_tests() {
    setup_cypht
    bootstrap_unit_tests
    setup_user
    setup_dovecot
    setup_postfix
    setup_site
}

# Main
echo "database: ${DB}"
echo "php-version: ${PHP_V}"
echo "test-arg: ${TEST_ARG}"

ARG="${TEST_ARG}"
case "$ARG" in
    phpunit)
        setup_unit_tests
    ;;
    selenium)
        setup_ui_tests
    ;;
    *)
        setup_unit_tests
    ;;
esac

sys_info
