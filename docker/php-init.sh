#!/bin/bash
set -e

echo "=== PHP Container Init ==="

# Ensure .env exists (copy from .env.docker if missing)
if [ ! -f .env ] && [ -f .env.docker ]; then
    echo "Creating .env from .env.docker..."
    cp .env.docker .env
fi

# Install composer dependencies
if [ -f composer.json ] && [ ! -d vendor ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Wait for MySQL to be ready
echo "Waiting for MySQL..."
until mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" ${DB_PASS:+-p"${DB_PASS}"} -e "SELECT 1" &>/dev/null; do
    echo "  MySQL not ready, retrying in 2s..."
    sleep 2
done
echo "  MySQL is ready."

# Run migrations
if [ -f database/migrate.sh ]; then
    echo "Running migrations..."
    bash database/migrate.sh up || echo "  WARNING: Migration had errors (may be partially applied)."
fi

# Start Apache in foreground
echo "Starting Apache..."
exec apache2-foreground
