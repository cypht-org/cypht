#!/bin/bash

systemctl stop dovecot.service
#echo "mail_location = maildir:/home/%u/Maildir" | tee --append /etc/dovecot/conf.d/10-mail.conf
SSL_CERT=/etc/ssl/certs/dovecot.pem
SSL_KEY=/etc/ssl/private/dovecot.pem
PATH=$PATH:/usr/bin/ssl
FQDN=cypht-test.org
MAILNAME=cypht-test.org

openssl req -new -x509 -days 365 -nodes -out $SSL_CERT -keyout $SSL_KEY \
	-subj "/C=US/ST=CyphtTest/L=US/O=Dovecot mail server/CN=$FQDN/OU=$FQDN/emailAddress=root@$MAILNAME" > /dev/null

chown root $SSL_CERT || true
chgrp dovecot $SSL_CERT || true
chmod 0644 $SSL_CERT || true
chown root $SSL_KEY || true
chgrp dovecot $SSL_KEY || true
chmod 0600 $SSL_KEY || true
systemctl start dovecot.service
