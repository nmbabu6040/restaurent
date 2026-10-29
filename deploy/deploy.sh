#!/usr/bin/env bash
# Update a VPS install. Run from anywhere as the deploy user:  ./deploy/deploy.sh
# Take a backup first (deploy/backup.sh) when the release contains database migrations.
set -euo pipefail
umask 0002   # new files stay group-writable so php-fpm and the queue worker (www-data) can use them

cd "$(dirname "$0")/.."

echo "==> Maintenance mode on"
php artisan down --retry=60 || true
trap 'echo "DEPLOY FAILED. The site is still in maintenance mode. Fix the problem, then run: php artisan up"' ERR

echo "==> Pull latest code"
git pull --ff-only

echo "==> PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Front-end assets (needs Node; or build locally and upload public/build instead)"
npm ci
npm run build

echo "==> Database migrations"
php artisan migrate --force

echo "==> Rebuild caches"
php artisan optimize

echo "==> Restart queue workers"
php artisan queue:restart

echo "==> Maintenance mode off"
php artisan up
trap - ERR

echo "==> Pre-flight check"
php artisan app:preflight || true

echo "Done. Reload PHP-FPM so OPcache sees the new code:  sudo systemctl reload php8.3-fpm"
