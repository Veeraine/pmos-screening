# PMOS Screening - container image for free hosting (e.g. Render).
# Uses SQLite by default (rebuilt with demo data on every restart). To keep data,
# set DB_CONNECTION=pgsql and DB_URL to a PostgreSQL database such as Neon.

# ---- Stage 1: PHP packages ----
FROM php:8.3-apache AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install packages first so this step is cached when only the code changes.
# Dev packages are kept because the demo data uses the Faker library.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-interaction

# ---- Stage 2: build the CSS and JavaScript ----
FROM node:22-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=app /var/www/html /app
RUN npm run build

# ---- Stage 3: the final image ----
FROM app
COPY --from=assets /app/public/build /var/www/html/public/build

# Serve the "public" folder.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod +x docker/start.sh

# Settings for the hosted demo. Any of these can be changed on the host.
ENV APP_NAME="PMOS Screening" \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    PORT=10000

EXPOSE 10000

CMD ["/var/www/html/docker/start.sh"]
