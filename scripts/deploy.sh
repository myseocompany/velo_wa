#!/usr/bin/env bash
# deploy.sh — Zero-downtime deploy for AriCRM production
# Usage: ./scripts/deploy.sh [--skip-build]
# Run from the project root on the VPS.

set -euo pipefail

COMPOSE="docker compose -f docker-compose.prod.yml"
SKIP_BUILD="${1:-}"

echo "==> [deploy] Pulling latest code..."
git pull origin main

if [[ "$SKIP_BUILD" != "--skip-build" ]]; then
    echo "==> [deploy] Building app image..."
    $COMPOSE build app
fi

echo "==> [deploy] Running database migrations..."
$COMPOSE run --rm app php artisan migrate --force

echo "==> [deploy] Caching config & routes..."
$COMPOSE run --rm app php artisan config:cache
$COMPOSE run --rm app php artisan route:cache
$COMPOSE run --rm app php artisan view:cache
$COMPOSE run --rm app php artisan event:cache

echo "==> [deploy] Restarting app containers (rolling)..."
$COMPOSE up -d --no-deps --remove-orphans app horizon scheduler reverb

echo "==> [deploy] Restarting nginx..."
$COMPOSE exec nginx nginx -s reload

echo "==> [deploy] Clearing application cache..."
$COMPOSE exec app php artisan cache:clear
$COMPOSE exec app php artisan queue:restart

echo "==> [deploy] Done! Checking health..."
sleep 3
curl -sf "http://localhost/up" && echo " ✓ App healthy" || echo " ✗ Health check failed"
