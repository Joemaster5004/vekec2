FROM composer:2 AS composer
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev ffmpeg unzip \
    && docker-php-ext-install mysqli curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
RUN cp -a vendor /opt/vekec-vendor
COPY . .
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/lms.ini
RUN mkdir -p storage && chown -R www-data:www-data storage
EXPOSE 80
