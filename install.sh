#!/bin/bash

# Script d'installation de la Plateforme de Données d'Entreprises
# Version 1.0

set -e

echo "🏗️  Installation de la Plateforme de Données d'Entreprises"
echo "============================================================="

# Vérifier les prérequis
echo "📋 Vérification des prérequis..."

# Vérifier Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker n'est pas installé"
    echo "💡 Installer Docker: https://docs.docker.com/get-docker/"
    exit 1
fi

# Vérifier Docker Compose
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose n'est pas installé"
    echo "💡 Installer Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi

echo "✅ Prérequis validés"

# Configuration
echo "🔧 Configuration..."

# Créer le fichier .env s'il n'existe pas
if [ ! -f .env ]; then
    cp .env.example .env
    echo "📝 Fichier .env créé - veuillez le remplir avec vos identifiants"
fi

# Créer les dossiers nécessaires
mkdir -p storage/{documents,logs,cache,backups}
mkdir -p database/migrations
mkdir -p docker/nginx

echo "📁 Structure des dossiers créée"

# Permissions
echo "🔐 Configuration des permissions..."
chmod -R 755 .
chmod -R 777 storage/

# Build et lancement des containers
echo "🐳 Construction et lancement des containers Docker..."
docker-compose build
docker-compose up -d web db

echo "⏳ Attente de l'initialisation de la base de données..."
sleep 30

# Vérifier que la DB est prête
while ! docker-compose exec -T db mysql -uroot -pentreprise_2025 -e "SELECT 1" >/dev/null 2>&1; do
    echo "⏳ Attente de MySQL..."
    sleep 5
done

echo "✅ Base de données initialisée"

# Test de l'API
echo "🧪 Test de l'installation..."
sleep 10

if curl -s http://localhost/backend/api/stats.php | grep -q "success"; then
    echo "✅ API fonctionnelle"
else
    echo "⚠️  Problème avec l'API - vérifier les logs"
fi

# Affichage des informations finales
echo ""
echo "🎉 Installation terminée avec succès!"
echo ""
echo "📍 URLs disponibles:"
echo "   - Application web: http://localhost"
echo "   - API: http://localhost/backend/api/"
echo "   - PhpMyAdmin: http://localhost:8080 (docker-compose --profile admin up -d phpmyadmin)"
echo ""
echo "🔧 Prochaines étapes:"
echo "1. Remplir le fichier .env avec vos identifiants INPI"
echo "2. Configurer les tâches cron de collecte"
echo "3. Importer vos premières données"
echo ""
echo "📚 Documentation: ./docs/"
echo "🔍 Logs: docker-compose logs -f"

# Proposer de lancer PhpMyAdmin
read -p "Voulez-vous lancer PhpMyAdmin pour administrer la base ? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    docker-compose --profile admin up -d phpmyadmin
    echo "✅ PhpMyAdmin disponible sur http://localhost:8080"
fi

echo "🚀 Installation terminée !"
