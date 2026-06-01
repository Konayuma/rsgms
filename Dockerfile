FROM php:8.2-apache

WORKDIR /var/www/html

COPY . .

RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql && \
    a2enmod rewrite

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/docker-entrypoint.sh

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
