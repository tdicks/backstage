#!/usr/bin/env bash

set -euo pipefail

composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

npm ci
npm run build

php artisan migrate --force --no-interaction
php artisan optimize
php artisan queue:restart