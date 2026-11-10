# Produktions-Image für oralcare-cms (Komodo-Stack „oralcare“).
# Enthält Code, vendor und gebaute Assets – kein Bind-Mount des Projektordners.
# Build-Kontext ist das Repo-Root:  docker build -f deploy/Dockerfile .

# ---------------------------------------------------------------------------
# Basis: PHP + Apache mit denselben Extensions wie das bisherige Image
# ---------------------------------------------------------------------------
FROM php:8.5-apache AS base

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libicu-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd intl mysqli pdo pdo_mysql xml zip \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deploy/php/prod.ini $PHP_INI_DIR/conf.d/zz-oralcare.ini
COPY deploy/apache/oralcare-env.conf /etc/apache2/conf-available/oralcare-env.conf
RUN a2enconf oralcare-env

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# PHP-Abhängigkeiten (ohne Dev-Pakete, exakt nach composer.lock)
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

# ---------------------------------------------------------------------------
# Frontend: Website (Encore) und Sulu-Admin (braucht JS aus vendor/sulu/sulu)
# ---------------------------------------------------------------------------
FROM node:20-bookworm-slim AS assets

WORKDIR /app
# Sulus Admin-Webpack liest composer.json/.lock (Version fürs Cache-Busting) und JS aus mehreren vendor-Paketen
COPY --from=vendor /var/www/html/vendor ./vendor
COPY composer.json composer.lock package.json package-lock.json webpack.config.js ./
COPY assets ./assets
RUN mkdir -p public/build

RUN npm ci --no-audit --no-fund && npm run build

WORKDIR /app/assets/admin
RUN if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi \
    && npm run build

# ---------------------------------------------------------------------------
# Laufzeit-Image
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Bundle-Assets nach public/bundles kopieren und den Cache für beide Sulu-Kontexte vorwärmen.
# Die echten Zugangsdaten kommen erst zur Laufzeit per Umgebungsvariable (%env()% wird zur Laufzeit gelesen).
RUN bin/adminconsole assets:install public --no-interaction \
    && bin/adminconsole cache:warmup --no-interaction \
    && bin/websiteconsole cache:warmup --no-interaction \
    && mkdir -p var/storage var/share var/indexes public/uploads \
    && chown -R www-data:www-data var public/bundles public/uploads

COPY deploy/docker-entrypoint.sh /usr/local/bin/oralcare-entrypoint
RUN chmod +x /usr/local/bin/oralcare-entrypoint

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -fsS -o /dev/null --max-time 8 http://localhost/robots.txt || exit 1

ENTRYPOINT ["oralcare-entrypoint"]
CMD ["apache2-foreground"]
