#!/bin/sh
set -e
PORT="${PORT:-80}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
# uploads may be a mounted volume (owned by root) — make it writable
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads || true
exec apache2-foreground
