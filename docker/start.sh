#!/bin/sh
# Starts the hosted demo: prepares the database and settings, then starts Apache.
set -e
cd /var/www/html

# A secret key is needed for logins. Make one if the host did not set it.
if [ -z "$APP_KEY" ]; then
    export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

# Use the address the host gives the site (Render sets RENDER_EXTERNAL_URL).
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

# Listen on the port the host asks for.
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Create the tables, and add the demo accounts and sample patients the first time.
# With SQLite the database file is made here; with PostgreSQL or MySQL (set by
# the host, e.g. a free Neon database) the data is kept between restarts.
if [ "$DB_CONNECTION" = "sqlite" ]; then
    touch "$DB_DATABASE"
fi
php artisan migrate --force
USERS=$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Models\User::count();')
if [ "$USERS" = "0" ]; then
    php artisan db:seed --force
    php artisan pmos:sample-report-data --force --seed=2026
fi

# Cache settings, routes and screens so pages load faster.
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache database

exec apache2-foreground
