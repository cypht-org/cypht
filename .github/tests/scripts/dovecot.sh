#!/bin/bash


sudo systemctl stop dovecot.service
sudo tee /etc/dovecot/conf.d/99-ci.conf > /dev/null << 'EOF'
auth_allow_cleartext = yes
auth_mechanisms = plain login
auth_username_format = %{user | username}
ssl = no
EOF

# Validate config before starting so CI prints exact errors if anything is wrong
sudo doveconf -n > /dev/null
sudo systemctl start dovecot.service
