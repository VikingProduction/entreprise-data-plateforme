#!/bin/bash

# Script d'installation automatique SANS Docker
# Plateforme de Données d'Entreprises
# Testé sur Ubuntu 20.04+ et Debian 11+

set -e

echo "🏗️  Installation Automatique - Entreprise Data Platform"
echo "====================================================="
echo "Installation sans Docker sur serveur Linux"
echo ""

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables
DOMAIN=""
DB_PASSWORD=""
INPI_USERNAME=""
INPI_PASSWORD=""
INSTALL_SSL=false

# Fonctions utilitaires
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Vérification des prérequis
check_prerequisites() {
    log_info "Vérification des prérequis..."

    # Vérifier si on est root
    if [[ $EUID -eq 0 ]]; then
        log_error "Ce script ne doit pas être exécuté en tant que root"
        log_info "Utilisez: sudo $0"
        exit 1
    fi

    # Vérifier sudo
    if ! sudo -v; then
        log_error "Vous devez avoir les droits sudo"
        exit 1
    fi

    # Vérifier la distribution Linux
    if [[ ! -f /etc/os-release ]]; then
        log_error "Distribution Linux non supportée"
        exit 1
    fi

    . /etc/os-release
    if [[ "$ID" != "ubuntu" && "$ID" != "debian" ]]; then
        log_warning "Distribution non testée: $ID $VERSION_ID"
        read -p "Continuer quand même? (y/N): " -n 1 -r
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
        echo
    fi

    log_success "Prérequis validés"
}

# Configuration interactive
configure_installation() {
    log_info "Configuration de l'installation..."
    echo

    # Nom de domaine
    while [[ -z "$DOMAIN" ]]; do
        read -p "Nom de domaine (ex: entreprise.mondomaine.com): " DOMAIN
        if [[ -z "$DOMAIN" ]]; then
            log_warning "Le nom de domaine est obligatoire"
        fi
    done

    # Mot de passe MySQL
    while [[ -z "$DB_PASSWORD" ]]; do
        read -s -p "Mot de passe MySQL (sera créé): " DB_PASSWORD
        echo
        if [[ ${#DB_PASSWORD} -lt 8 ]]; then
            log_warning "Le mot de passe doit faire au moins 8 caractères"
            DB_PASSWORD=""
        fi
    done

    # Identifiants INPI
    echo
    log_info "Identifiants API INPI (créer un compte sur data.inpi.fr)"
    while [[ -z "$INPI_USERNAME" ]]; do
        read -p "Email INPI: " INPI_USERNAME
    done

    while [[ -z "$INPI_PASSWORD" ]]; do
        read -s -p "Mot de passe INPI: " INPI_PASSWORD
        echo
    done

    # SSL
    echo
    read -p "Installer SSL avec Let's Encrypt? (y/N): " -n 1 -r
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        INSTALL_SSL=true
    fi
    echo

    log_success "Configuration terminée"
}

# Installation des paquets
install_packages() {
    log_info "Installation des paquets système..."

    # Mise à jour
    sudo apt update

    # Installation PHP 8.2
    sudo apt install -y software-properties-common
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt update

    sudo apt install -y \
        php8.2 php8.2-fpm php8.2-mysql php8.2-curl \
        php8.2-json php8.2-mbstring php8.2-xml php8.2-zip \
        php8.2-gd php8.2-bcmath php8.2-intl \
        mysql-server mysql-client \
        nginx \
        curl wget unzip git cron \
        ufw fail2ban

    if [[ "$INSTALL_SSL" == true ]]; then
        sudo apt install -y certbot python3-certbot-nginx
    fi

    log_success "Paquets installés"
}

# Configuration MySQL
configure_mysql() {
    log_info "Configuration de MySQL..."

    # Démarrer MySQL
    sudo systemctl start mysql
    sudo systemctl enable mysql

    # Configuration sécurisée automatique
    sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASSWORD';"
    sudo mysql -u root -p$DB_PASSWORD -e "DELETE FROM mysql.user WHERE User='';"
    sudo mysql -u root -p$DB_PASSWORD -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    sudo mysql -u root -p$DB_PASSWORD -e "DROP DATABASE IF EXISTS test;"
    sudo mysql -u root -p$DB_PASSWORD -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%';"
    sudo mysql -u root -p$DB_PASSWORD -e "FLUSH PRIVILEGES;"

    # Créer la base de données
    sudo mysql -u root -p$DB_PASSWORD -e "CREATE DATABASE entreprise_data CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    sudo mysql -u root -p$DB_PASSWORD -e "CREATE USER 'entreprise_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
    sudo mysql -u root -p$DB_PASSWORD -e "GRANT ALL PRIVILEGES ON entreprise_data.* TO 'entreprise_user'@'localhost';"
    sudo mysql -u root -p$DB_PASSWORD -e "FLUSH PRIVILEGES;"

    log_success "MySQL configuré"
}

# Configuration PHP
configure_php() {
    log_info "Configuration de PHP..."

    # Configuration PHP-FPM
    sudo tee /etc/php/8.2/fpm/pool.d/www.conf > /dev/null <<EOF
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 10
pm.max_requests = 500

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[max_execution_time] = 300
EOF

    # Configuration PHP CLI
    sudo sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/cli/php.ini
    sudo sed -i 's/max_execution_time = .*/max_execution_time = 0/' /etc/php/8.2/cli/php.ini
    sudo sed -i 's/;date.timezone.*/date.timezone = Europe\/Paris/' /etc/php/8.2/cli/php.ini

    # Démarrer PHP-FPM
    sudo systemctl start php8.2-fpm
    sudo systemctl enable php8.2-fpm

    log_success "PHP configuré"
}

# Déploiement de l'application
deploy_application() {
    log_info "Déploiement de l'application..."

    # Créer le dossier web
    sudo mkdir -p /var/www/entreprise-platform
    cd /var/www/entreprise-platform

    # Copier les fichiers (adapter selon votre méthode)
    if [[ -f "../entreprise-data-platform-complet.zip" ]]; then
        sudo unzip ../entreprise-data-platform-complet.zip
        sudo mv entreprise-data-platform/* .
        sudo rmdir entreprise-data-platform
    elif [[ -d "../entreprise-data-platform" ]]; then
        sudo cp -r ../entreprise-data-platform/* .
    else
        log_error "Fichiers de l'application non trouvés"
        log_info "Placez le ZIP ou le dossier à côté de ce script"
        exit 1
    fi

    # Permissions
    sudo chown -R www-data:www-data /var/www/entreprise-platform
    sudo chmod -R 755 /var/www/entreprise-platform

    # Créer les dossiers nécessaires
    sudo mkdir -p storage/{documents,logs,cache,backups}
    sudo chown -R www-data:www-data storage
    sudo chmod -R 777 storage

    # Configuration
    sudo cp .env.example .env
    sudo tee .env > /dev/null <<EOF
DB_HOST=localhost
DB_NAME=entreprise_data
DB_USER=entreprise_user
DB_PASS=$DB_PASSWORD

INPI_USERNAME=$INPI_USERNAME
INPI_PASSWORD=$INPI_PASSWORD

APP_NAME="Entreprise Data Platform"
APP_URL=http://$DOMAIN
APP_ENV=production

DOWNLOAD_DOCUMENTS=true
MAX_SEARCH_RESULTS=100
CACHE_SEARCH_DURATION=3600
API_RATE_LIMIT=100
EOF

    sudo chown www-data:www-data .env

    log_success "Application déployée"
}

# Import de la base de données
import_database() {
    log_info "Import du schéma de base de données..."

    mysql -u entreprise_user -p$DB_PASSWORD entreprise_data < database/schema.sql

    # Vérifier l'import
    TABLES_COUNT=$(mysql -u entreprise_user -p$DB_PASSWORD -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='entreprise_data'")

    if [[ $TABLES_COUNT -gt 10 ]]; then
        log_success "Base de données importée ($TABLES_COUNT tables)"
    else
        log_error "Erreur lors de l'import de la base de données"
        exit 1
    fi
}

# Configuration Nginx
configure_nginx() {
    log_info "Configuration de Nginx..."

    sudo tee /etc/nginx/sites-available/entreprise-platform > /dev/null <<EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;

    root /var/www/entreprise-platform;
    index index.php index.html;

    access_log /var/log/nginx/entreprise-platform-access.log;
    error_log /var/log/nginx/entreprise-platform-error.log;

    server_tokens off;
    client_max_body_size 50M;

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location /backend/api/ {
        try_files \$uri \$uri/ @api;
        add_header Access-Control-Allow-Origin *;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization";
    }

    location @api {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$uri;
        include fastcgi_params;
    }

    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;

        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
    }

    location ~ /\.(ht|git|env) {
        deny all;
    }

    location /storage/ {
        internal;
        alias /var/www/entreprise-platform/storage/;
    }

    gzip on;
    gzip_vary on;
    gzip_min_length 1000;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/json;
}
EOF

    # Activer le site
    sudo ln -sf /etc/nginx/sites-available/entreprise-platform /etc/nginx/sites-enabled/
    sudo rm -f /etc/nginx/sites-enabled/default

    # Tester et redémarrer
    sudo nginx -t
    sudo systemctl restart nginx
    sudo systemctl enable nginx

    log_success "Nginx configuré"
}

# Configuration des tâches CRON
configure_cron() {
    log_info "Configuration des tâches CRON..."

    # Créer le crontab pour www-data
    sudo -u www-data crontab - <<EOF
# Collecte quotidienne à 2h du matin
0 2 * * * cd /var/www/entreprise-platform && /usr/bin/php backend/cron/daily_import.php >> storage/logs/cron.log 2>&1

# Nettoyage des logs (tous les dimanches à 4h)
0 4 * * 0 cd /var/www/entreprise-platform && /usr/bin/php backend/cron/cleanup.php >> storage/logs/cron.log 2>&1

# Sauvegarde de la base de données (tous les jours à 5h)
0 5 * * * /usr/bin/mysqldump -u entreprise_user -p$DB_PASSWORD entreprise_data > /var/www/entreprise-platform/storage/backups/db_\$(date +\%Y\%m\%d).sql
EOF

    log_success "CRON configuré"
}

# Configuration SSL
configure_ssl() {
    if [[ "$INSTALL_SSL" == true ]]; then
        log_info "Configuration SSL avec Let's Encrypt..."

        sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN

        # Renouvellement automatique
        (sudo crontab -l 2>/dev/null; echo "0 3 * * * /usr/bin/certbot renew --quiet") | sudo crontab -

        log_success "SSL configuré"
    fi
}

# Sécurisation
configure_security() {
    log_info "Configuration de la sécurité..."

    # Firewall
    sudo ufw --force reset
    sudo ufw allow 22/tcp
    sudo ufw allow 80/tcp
    sudo ufw allow 443/tcp
    sudo ufw --force enable

    # Fail2ban
    sudo systemctl enable fail2ban
    sudo systemctl start fail2ban

    log_success "Sécurité configurée"
}

# Tests finaux
run_tests() {
    log_info "Tests de l'installation..."

    # Test des services
    if sudo systemctl is-active --quiet mysql php8.2-fpm nginx; then
        log_success "Tous les services sont actifs"
    else
        log_error "Certains services ne sont pas actifs"
        sudo systemctl status mysql php8.2-fpm nginx
    fi

    # Test web
    if curl -f -s http://$DOMAIN > /dev/null; then
        log_success "Site web accessible"
    else
        log_warning "Site web non accessible (vérifiez DNS)"
    fi

    # Test API
    if curl -f -s http://$DOMAIN/backend/api/stats.php | grep -q "success"; then
        log_success "API fonctionnelle"
    else
        log_warning "API non fonctionnelle"
    fi

    # Test base de données
    if mysql -u entreprise_user -p$DB_PASSWORD -e "USE entreprise_data; SELECT COUNT(*) FROM companies;" > /dev/null 2>&1; then
        log_success "Base de données accessible"
    else
        log_error "Problème avec la base de données"
    fi
}

# Affichage final
display_summary() {
    echo
    echo "🎉 INSTALLATION TERMINÉE AVEC SUCCÈS !"
    echo "====================================="
    echo
    echo "📍 URLs d'accès :"
    if [[ "$INSTALL_SSL" == true ]]; then
        echo "   Interface web : https://$DOMAIN"
        echo "   API REST      : https://$DOMAIN/backend/api/"
    else
        echo "   Interface web : http://$DOMAIN"
        echo "   API REST      : http://$DOMAIN/backend/api/"
    fi
    echo
    echo "🔧 Informations techniques :"
    echo "   Dossier web   : /var/www/entreprise-platform"
    echo "   Logs          : /var/www/entreprise-platform/storage/logs/"
    echo "   Documents     : /var/www/entreprise-platform/storage/documents/"
    echo "   Base MySQL    : entreprise_data"
    echo
    echo "📋 Commandes utiles :"
    echo "   Logs Nginx    : sudo tail -f /var/log/nginx/entreprise-platform-*.log"
    echo "   Status        : sudo systemctl status mysql php8.2-fpm nginx"
    echo "   Redémarrer    : sudo systemctl restart mysql php8.2-fpm nginx"
    echo "   Import manuel : sudo -u www-data php /var/www/entreprise-platform/backend/cron/daily_import.php"
    echo
    echo "🎯 Prochaines étapes :"
    echo "1. Configurer DNS pour pointer vers ce serveur"
    echo "2. Tester la recherche d'entreprises"
    echo "3. Vérifier les logs de collecte dans 24h"
    echo
    log_success "Installation terminée !"
}

# Fonction principale
main() {
    check_prerequisites
    configure_installation
    install_packages
    configure_mysql
    configure_php
    deploy_application
    import_database
    configure_nginx
    configure_cron
    configure_ssl
    configure_security
    run_tests
    display_summary
}

# Gestion des erreurs
trap 'log_error "Installation interrompue"; exit 1' ERR INT TERM

# Lancement
main "$@"
