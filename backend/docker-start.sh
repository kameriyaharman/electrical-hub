#!/bin/sh
set -e
PORT="${PORT:-80}"
# Railway quirk: make sure only one Apache MPM (prefork, needed by mod_php) is enabled
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then a2enmod mpm_prefork >/dev/null 2>&1 || true; fi
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
# uploads may be a mounted volume (owned by root) — make it writable
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads || true
# Old server retired: when MOVED_TO is set (e.g. https://electricalmarkethub.in) every page redirects there and
# /api/ + /uploads/ are passed through to it, so old app installs keep working on the one live database.
if [ -n "${MOVED_TO}" ]; then
  a2enmod proxy proxy_http ssl >/dev/null 2>&1 || true
  cat > /etc/apache2/conf-enabled/zz-moved.conf <<CONF
SSLProxyEngine On
ProxyPreserveHost Off
ProxyPass /api/ ${MOVED_TO}/api/
ProxyPassReverse /api/ ${MOVED_TO}/api/
ProxyPass /uploads/ ${MOVED_TO}/uploads/
ProxyPassReverse /uploads/ ${MOVED_TO}/uploads/
RedirectMatch 301 ^/(?!api/|uploads/)(.*)\$ ${MOVED_TO}/\$1
CONF
  rm -f /var/www/html/.htaccess
fi
exec apache2-foreground
