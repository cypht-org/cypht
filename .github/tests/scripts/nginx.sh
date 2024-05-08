#!/bin/bash

# Check if nginx is installed
if ! command -v nginx &> /dev/null
then
    echo "Nginx is not installed"
    exit 1
fi

# Stop Nginx
service nginx stop
echo "Nginx stopped"

# Copy Nginx configuration
cp .github/tests/selenium/nginx/nginx-site.conf /etc/nginx/sites-available/default
echo "Nginx configuration copied"

# Create directory for nginx config
mkdir -p /etc/nginx/nginxconfig
echo "Nginx directory created"

# Copy PHP FastCGI config
cp .github/tests/selenium/nginx/php_fastcgi.conf /etc/nginx/nginxconfig/php_fastcgi.conf
echo "PHP FastCGI config copied"

# Replace PHP version placeholder in Nginx config
sed -e "s?%VERSION%?${PHP_V}?g" --in-place /etc/nginx/sites-available/default
echo "PHP version placeholder replaced"

# Create symbolic link
ln -sf "$(pwd)" /var/www/cypht
echo "Symbolic link created"

# Start Nginx
sudo systemctl start nginx
echo "Nginx started"
