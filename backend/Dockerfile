FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && { echo 'upload_max_filesize=12M'; echo 'post_max_size=14M'; echo 'memory_limit=256M'; echo 'expose_php=Off'; echo 'date.timezone=Asia/Kolkata'; } > /usr/local/etc/php/conf.d/electricalhub.ini \
 && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

COPY . /var/www/html/
COPY docker-start.sh /usr/local/bin/eh-start
RUN chmod +x /usr/local/bin/eh-start && rm -f /var/www/html/Dockerfile /var/www/html/docker-start.sh \
 && chown -R www-data:www-data /var/www/html

CMD ["eh-start"]
