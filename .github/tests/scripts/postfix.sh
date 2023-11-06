#!/bin/bash

sudo systemctl stop postfix.service
sudo sed -i 's/#myorigin/myorigin/g' /etc/postfix/main.cf
sudo -H postconf virtual_transport=lmtp:unix:private/dovecot-lmtp
sudo systemctl start postfix.service