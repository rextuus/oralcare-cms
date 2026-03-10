FROM php:8.4-apache

# Apache DocumentRoot auf /public setzen
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Apache Module aktivieren
RUN a2enmod rewrite

# --- Node.js, PHP-Module & Bild-Libraries installieren ---
RUN apt-get update && apt-get install -y \
    git zip unzip \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    libicu-dev libxml2-dev libzip-dev curl \
    && curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    # GD mit allen Treibern konfigurieren und installieren
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd intl mysqli pdo pdo_mysql xml zip

# Composer installieren
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP Tuning
RUN echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/docker-php-ram.ini

WORKDIR /var/www/html

# Struktur anlegen und Berechtigungen setzen
# Wir fügen public/uploads/media hinzu, damit Sulu sofort loslegen kann
RUN mkdir -p var/cache var/log var/sessions var/storage public/build public/uploads/media && \
    chown -R www-data:www-data var public/uploads public/build && \
    chmod -R 775 var public/uploads public/build

# Sicherstellen, dass der Webserver-User Schreibrechte auf den gesamten Workspace für Cache-Files hat
RUN chown www-data:www-data /var/www/html
