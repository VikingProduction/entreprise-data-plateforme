#!/bin/bash

# Entrypoint script for Docker container

echo "🚀 Démarrage de la plateforme Entreprise Data Platform"

# Create necessary directories
mkdir -p /var/www/html/storage/{logs,cache,documents,backups}

# Set permissions
chown -R www-data:www-data /var/www/html/storage
chmod -R 777 /var/www/html/storage

# Wait for database to be ready
echo "⏳ Attente de la base de données..."
while ! mysqladmin ping -h"$DB_HOST" --silent; do
    sleep 1
done

echo "✅ Base de données prête"

# Install composer dependencies if composer.json exists
if [ -f composer.json ]; then
    echo "📦 Installation des dépendances Composer..."
    composer install --no-dev --optimize-autoloader
fi

# Start supervisor
echo "🎯 Démarrage des services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
