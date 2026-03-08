FROM php:8.4-apache

# Apache DocumentRoot auf /public setzen
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Apache Module aktivieren
RUN a2enmod rewrite

# --- NEU: Node.js & PHP-Module installieren ---
RUN apt-get update && apt-get install -y \
    git zip unzip libpng-dev libicu-dev libxml2-dev libzip-dev curl \
    && curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install gd intl mysqli pdo pdo_mysql xml zip

# Composer manuell installieren
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Memory Limit erhöhen
RUN echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/docker-php-ram.ini

WORKDIR /var/www/html

# Rechte für Sulu vorbereiten
RUN mkdir -p var/cache var/log var/sessions var/storage public/build public/uploads && \
    chown -R www-data:www-data var public/uploads public/build && \
    chmod -R 775 var public/uploads public/build
