#!/bin/bash

systemctl stop dovecot.service
sudo echo "disable_plaintext_auth = no" >> /etc/dovecot/conf.d/10-auth.conf
sudo sed -i "s/auth_mechanisms = plain/auth_mechanisms = plain login/g" /etc/dovecot/conf.d/10-auth.conf
sudo sed -i "s/ssl = yes/ssl = no/g" /etc/dovecot/conf.d/10-ssl.conf
systemctl start dovecot.service