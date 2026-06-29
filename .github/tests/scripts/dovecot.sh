#!/bin/bash

sudo systemctl stop dovecot.service
echo "disable_plaintext_auth = no" | sudo tee -a /etc/dovecot/conf.d/10-auth.conf
sudo sed -i "s/auth_mechanisms = plain/auth_mechanisms = plain login/g" /etc/dovecot/conf.d/10-auth.conf
sudo sed -i "s/ssl = yes/ssl = no/g" /etc/dovecot/conf.d/10-ssl.conf
# Strip domain from username so testuser@localhost authenticates as testuser in PAM
echo "auth_username_format = %n" | sudo tee -a /etc/dovecot/conf.d/10-auth.conf
sudo systemctl start dovecot.service
