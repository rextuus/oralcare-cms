FROM php:8.4-apache

# Apache DocumentRoot auf /public setzen
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Apache Module aktivieren
RUN a2enmod rewrite

# PHP-Module für Sulu/Symfony installieren
RUN apt-get update && apt-get install -y \
    git zip unzip libpng-dev libicu-dev libxml2-dev libzip-dev \
    && docker-php-ext-install gd intl mysqli pdo pdo_mysql xml zip

# Composer installieren
COPY --from=composer:latest /usr/local/bin/composer /usr/local/bin/composer

# Memory Limit erhöhen
RUN echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/docker-php-ram.ini

WORKDIR /var/www/html
