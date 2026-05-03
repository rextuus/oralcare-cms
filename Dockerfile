FROM php:8.4-apache

# Apache DocumentRoot auf /public setzen
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Apache Module aktivieren
RUN a2enmod rewrite

# --- System-Abhängigkeiten, Node.js & PHP-Module ---
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    # Notwendig für die Bildverarbeitung (Sulu Media)
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    # Weitere Symfony/Sulu Abhängigkeiten
    libicu-dev \
    libxml2-dev \
    libzip-dev \
    curl \
    && curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    # GD mit allen Treibern konfigurieren und installieren (Löst dein JPG-Problem)
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd intl mysqli pdo pdo_mysql xml zip

# Composer installieren
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP Tuning (Sulu benötigt für die Bildgenerierung oft mehr RAM)
RUN echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/docker-php-ram.ini
RUN echo 'upload_max_filesize = 32M' >> /usr/local/etc/php/conf.d/docker-php-ram.ini
RUN echo 'post_max_size = 32M' >> /usr/local/etc/php/conf.d/docker-php-ram.ini

WORKDIR /var/www/html

# Struktur anlegen und Berechtigungen setzen
# WICHTIG: var/uploads für die Originale und public/uploads für die Thumbnails
RUN mkdir -p var/cache var/log var/sessions var/storage var/uploads public/build public/uploads/media && \
    chown -R www-data:www-data var public/uploads public/build && \
    chmod -R 775 var public/uploads public/build

# Sicherstellen, dass der Webserver-User den gesamten Workspace bearbeiten kann
RUN chown -R www-data:www-data /var/www/html
