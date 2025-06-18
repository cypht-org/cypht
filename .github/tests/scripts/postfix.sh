#!/bin/bash

# Stop Postfix to safely apply changes
sudo systemctl stop postfix.service

# Enable myorigin if commented
sudo sed -i 's/#myorigin/myorigin/g' /etc/postfix/main.cf

# Set virtual transport to Dovecot LMTP
sudo -H postconf virtual_transport=lmtp:unix:private/dovecot-lmtp

# Enable SMTP AUTH via Dovecot
sudo -H postconf smtpd_sasl_type=dovecot
sudo -H postconf smtpd_sasl_path=private/auth
sudo -H postconf smtpd_sasl_auth_enable=yes
sudo -H postconf smtpd_sasl_security_options=noanonymous
sudo -H postconf broken_sasl_auth_clients=yes

# Allow authenticated users to relay mail
sudo -H postconf "smtpd_recipient_restrictions=permit_sasl_authenticated,permit_mynetworks,reject_unauth_destination"

# Ensure Dovecot provides the Postfix auth socket with correct permissions
sudo sed -i '/^service auth {/,/^}/ {
    /unix_listener \/var\/spool\/postfix\/private\/auth/!b
    n
    s/mode = .*/mode = 0660/
    s/user = .*/user = postfix/
    s/group = .*/group = postfix/
}' /etc/dovecot/conf.d/10-master.conf || true

# Enable TLS for SMTP with fallback
sudo postconf -e "smtpd_use_tls=no"
sudo postconf -e "smtpd_tls_security_level=none"
sudo postconf -e "smtpd_tls_auth_only=no"        # Allow AUTH without TLS for testing

# Generate self-signed certificate for optional STARTTLS
CERT_FILE="/etc/ssl/certs/postfix.pem"
KEY_FILE="/etc/ssl/private/postfix.key"
if [ ! -f "$CERT_FILE" ] || [ ! -f "$KEY_FILE" ]; then
    sudo openssl req -new -x509 -days 365 -nodes \
        -out "$CERT_FILE" -keyout "$KEY_FILE" \
        -subj "/CN=localhost"
fi
sudo postconf -e "smtpd_tls_cert_file=$CERT_FILE"
sudo postconf -e "smtpd_tls_key_file=$KEY_FILE"

# Allow plaintext authentication in Dovecot
sudo sed -i 's/ssl = yes/ssl = no/' /etc/dovecot/conf.d/10-ssl.conf
sudo sed -i 's/disable_plaintext_auth = yes/disable_plaintext_auth = no/' /etc/dovecot/conf.d/10-auth.conf

# Restart services to apply changes
sudo systemctl restart dovecot
sudo systemctl restart postfix