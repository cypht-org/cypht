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
	STATUS_TITLE "Setup Cypht"
	mkdir -p /tmp/hm3/{users,attachments,app_data}
	chmod -R 0777 /tmp/hm3
	cp .github/tests/config/hm3.ini "$(pwd)"
	if [ "$DB" = "postgres" ]; then
		# hm3.ini
		sed -i 's/db_driver=db/db_driver=pgsql/' hm3.ini
		sed -i 's/db_connection_type=db/db_connection_type=host/' hm3.ini
		sed -i 's/db_host=db/db_host=127.0.0.1/' hm3.ini
		sed -i 's/db_port=db/db_port=5432/' hm3.ini
		# mocks.php
		sed -i 's/test_db_type/host/' tests/phpunit/mocks.php
		sed -i 's/test_db_driver/pgsql/' tests/phpunit/mocks.php
	elif [ "$DB" = "mysql" ]; then
		# hm3.ini
		sed -i 's/db_driver=db/db_driver=mysql/' hm3.ini
		sed -i 's/db_connection_type=db/db_connection_type=host/' hm3.ini
		sed -i 's/db_host=db/db_host=127.0.0.1/' hm3.ini
		sed -i 's/db_port=db/db_port=3306/' hm3.ini
		# mocks.php
		sed -i 's/test_db_type/host/' tests/phpunit/mocks.php
		sed -i 's/test_db_driver/mysql/' tests/phpunit/mocks.php
	elif [ "$DB" = "sqlite" ]; then
		# hm3.ini
		sed -i 's/db_driver=db/db_driver=sqlite/' hm3.ini
		sed -i 's/db_connection_type=db/db_connection_type=socket/' hm3.ini
		sed -i 's/db_socket=db/db_socket=\/tmp\/hm3\/test.db/' hm3.ini
		# mocks.php
		sed -i 's/test_db_type/socket/' tests/phpunit/mocks.php
		sed -i 's/test_db_driver/sqlite/' tests/phpunit/mocks.php
	else
		STATUS_ERROR
		echo "DB environment variable not found"
		exit 1
	fi
	cp .github/tests/selenium/creds.py tests/selenium/creds.py
	php scripts/config_gen.php 1>/dev/null
	STATUS_DONE
}

# Setup base data needed by the phpunit tests
bootstrap_unit_tests() {
	if [ "$DB" = "postgres" ]; then
		STATUS_TITLE "Setup ($DB)"
		sudo usermod -aG docker postgres
		# Init Postgresql
		echo "host cypht_test cypht_test 127.0.0.1/32 md5" | sudo tee -a /etc/postgresql/"$(psql --version | awk '{print $3}' | awk -F. '{print $1}')"/main/pg_hba.conf 1>/dev/null
		sudo systemctl start postgresql.service
		# Init DB
		sudo -u postgres psql -c "CREATE DATABASE cypht_test;" -c "CREATE USER cypht_test WITH ENCRYPTED PASSWORD 'cypht_test';" -c "GRANT all privileges ON DATABASE cypht_test TO cypht_test;" -c "ALTER DATABASE cypht_test OWNER TO cypht_test;" 1>/dev/null
		PGPASSWORD=cypht_test psql -h 127.0.0.1 -U cypht_test -d cypht_test -f .github/tests/db/postgresql/cypht_test.sql 1>/dev/null
		if [ "$(sudo systemctl is-active postgresql.service)" == "active" ]; then
			STATUS_DONE
		else
			STATUS_ERROR
			exit 1
		fi
	elif [ "$DB" = "mysql" ]; then
		STATUS_TITLE "Setup ($DB)"
		# Init Mysql
		sudo systemctl start mysql.service
		# Init DB
		sudo mysql --defaults-extra-file=.github/tests/db/mysql/root_my.cnf -e "CREATE DATABASE cypht_test; CREATE USER 'cypht_test'@'localhost' IDENTIFIED BY 'cypht_test'; GRANT ALL PRIVILEGES ON cypht_test.* TO 'cypht_test'@'localhost'; FLUSH PRIVILEGES;"
		mysql --defaults-extra-file=.github/tests/db/mysql/cypht_test_my.cnf -e 'source .github/tests/db/mysql/cypht_test.sql'
		if [ "$(sudo systemctl is-active mysql.service)" == "active" ]; then
			STATUS_DONE
		else
			STATUS_ERROR
			exit 1
		fi
	elif [ "$DB" = "sqlite" ]; then
		STATUS_TITLE "Setup ($DB)"
		touch /tmp/hm3/test.db
		sqlite3 /tmp/hm3/test.db <.github/tests/db/sqlite/cypht_test.sql
		STATUS_DONE
	else
		echo "DB environment variable not found"
		exit 1
	fi
	echo '+2IdQejfHu4FNYOA3tm0DJVQNg92gcpJf8ETeVj+HK0OU6J5iaV/J823rLm8+5Et7tQLoCCoZwElGTH7N2P2M4JMct1jRyWgjqJQn9FYlovFYj/8fLwkixGo+VMNIKsUwJ42GXTj61nn0Rf4+FO688SfAR5LhaLTXlR6XZ9mJD2/2RX1X+Z1JVl7SrqELgE8wnz5IYCrzqBbgK4MDn86rTtPM9jie3gFS9viMZ7OQRENbXLvwBaIXNLvQlZZn2JBdzXF1spoLnSlq8P0pYXlDig==' >tests/phpunit/data/testuser.txt
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
	sudo systemctl start nginx.service
	if [ "$(curl -s -o /dev/null -w '%{http_code}' 'http://cypht-test.org')" -eq 200 ]; then
		STATUS_DONE
	else
		STATUS_ERROR
		exit 1
	fi
}

##### UI END #####

# output some system info
sys_info() {
	echo -e "\033[0;34mSystem Info: \033[0m"
	sudo netstat -lntp
}

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

case "${TEST_ARG}" in
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