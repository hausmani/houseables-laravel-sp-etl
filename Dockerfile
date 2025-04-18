# Used for prod build.
FROM jkaninda/laravel-php-fpm:8.3

WORKDIR /var/www/html

COPY . /var/www/html
RUN composer install

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
