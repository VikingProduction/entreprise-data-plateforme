# 🔧 Installation Manuelle Sans Docker

## Guide d'installation pas à pas pour serveur Linux

### Prérequis système

- **OS** : Ubuntu 20.04+ / Debian 11+ / CentOS 8+
- **PHP** : 8.2 ou supérieur
- **MySQL** : 8.0 ou supérieur  
- **Nginx** : 1.18 ou supérieur
- **RAM** : 2 Go minimum
- **Espace** : 10 Go minimum

### 1. Installation des composants

#### Ubuntu/Debian
```bash
# Mise à jour du système
sudo apt update && sudo apt upgrade -y

# Installation PHP 8.2
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-curl \
    php8.2-json php8.2-mbstring php8.2-xml php8.2-zip \
    php8.2-gd php8.2-bcmath php8.2-intl

# Installation MySQL 8.0
sudo apt install -y mysql-server mysql-client

# Installation Nginx
sudo apt install -y nginx

# Outils système
sudo apt install -y curl wget unzip git cron
```

#### CentOS/RHEL
```bash
# Installation des dépôts
sudo dnf install -y epel-release
sudo dnf module enable php:8.2 -y

# Installation PHP 8.2
sudo dnf install -y php php-fpm php-mysqlnd php-curl \
    php-json php-mbstring php-xml php-zip \
    php-gd php-bcmath php-intl

# Installation MySQL 8.0
sudo dnf install -y mysql-server mysql

# Installation Nginx
sudo dnf install -y nginx

# Outils système
sudo dnf install -y curl wget unzip git cronie
```

### 2. Configuration MySQL

```bash
# Démarrer MySQL
sudo systemctl start mysql
sudo systemctl enable mysql

# Sécuriser l'installation
sudo mysql_secure_installation

# Créer la base de données
sudo mysql -u root -p
```

```sql
-- Dans MySQL
CREATE DATABASE entreprise_data CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'entreprise_user'@'localhost' IDENTIFIED BY 'VotreMotDePasseSecurise123!';
GRANT ALL PRIVILEGES ON entreprise_data.* TO 'entreprise_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Configuration PHP

```bash
# Configurer PHP-FPM
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

```ini
# Dans /etc/php/8.2/fpm/pool.d/www.conf
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

# Paramètres PHP
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[max_execution_time] = 300
```

```bash
# Configurer PHP CLI
sudo nano /etc/php/8.2/cli/php.ini
```

```ini
# Dans /etc/php/8.2/cli/php.ini
memory_limit = 512M
max_execution_time = 0
date.timezone = Europe/Paris
```

```bash
# Démarrer PHP-FPM
sudo systemctl start php8.2-fpm
sudo systemctl enable php8.2-fpm
```

### 4. Déploiement de l'application

```bash
# Créer le dossier web
sudo mkdir -p /var/www/entreprise-platform
cd /var/www/entreprise-platform

# Télécharger et extraire (remplacer par votre méthode)
# Si vous avez le ZIP :
sudo unzip /path/to/entreprise-data-platform-complet.zip
sudo mv entreprise-data-platform/* .
sudo rmdir entreprise-data-platform

# Ou cloner depuis Git :
# sudo git clone https://github.com/votre-repo/entreprise-platform.git .

# Permissions
sudo chown -R www-data:www-data /var/www/entreprise-platform
sudo chmod -R 755 /var/www/entreprise-platform
sudo chmod -R 777 /var/www/entreprise-platform/storage

# Créer les dossiers nécessaires
sudo mkdir -p storage/{documents,logs,cache,backups}
sudo chown -R www-data:www-data storage
```

### 5. Configuration de l'environnement

```bash
# Copier le fichier de configuration
sudo cp .env.example .env
sudo chown www-data:www-data .env

# Éditer la configuration
sudo nano .env
```

```env
# Dans .env
DB_HOST=localhost
DB_NAME=entreprise_data
DB_USER=entreprise_user
DB_PASS=VotreMotDePasseSecurise123!

# Identifiants API INPI (obligatoire)
INPI_USERNAME=votre_email@domain.com
INPI_PASSWORD=votre_mot_de_passe_inpi

# Configuration application
APP_NAME="Entreprise Data Platform"
APP_URL=http://votre-domaine.com
APP_ENV=production

DOWNLOAD_DOCUMENTS=true
MAX_SEARCH_RESULTS=100
CACHE_SEARCH_DURATION=3600
API_RATE_LIMIT=100
```

### 6. Import de la base de données

```bash
# Importer le schéma
mysql -u entreprise_user -p entreprise_data < database/schema.sql

# Vérifier l'import
mysql -u entreprise_user -p -e "USE entreprise_data; SHOW TABLES;"
```

### 7. Configuration Nginx

```bash
# Créer le fichier de configuration
sudo nano /etc/nginx/sites-available/entreprise-platform
```

```nginx
server {
    listen 80;
    server_name votre-domaine.com www.votre-domaine.com;

    root /var/www/entreprise-platform;
    index index.php index.html;

    # Logs
    access_log /var/log/nginx/entreprise-platform-access.log;
    error_log /var/log/nginx/entreprise-platform-error.log;

    # Sécurité
    server_tokens off;
    client_max_body_size 50M;

    # Gestion des fichiers statiques
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary Accept-Encoding;
        access_log off;
    }

    # API endpoints
    location /backend/api/ {
        try_files $uri $uri/ @api;

        # Headers CORS
        add_header Access-Control-Allow-Origin *;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization";
    }

    # Route pour l'API
    location @api {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$uri;
        include fastcgi_params;
    }

    # PHP processing
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;

        # Optimizations
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
    }

    # Bloque l'accès aux fichiers sensibles
    location ~ /\.(ht|git|env) {
        deny all;
    }

    location /storage/ {
        internal;
        alias /var/www/entreprise-platform/storage/;
    }

    # Documents PDF
    location /documents/ {
        alias /var/www/entreprise-platform/storage/documents/;
    }

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1000;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/json
        application/xml+rss;
}
```

```bash
# Activer le site
sudo ln -s /etc/nginx/sites-available/entreprise-platform /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Tester la configuration
sudo nginx -t

# Redémarrer Nginx
sudo systemctl restart nginx
sudo systemctl enable nginx
```

### 8. Configuration des tâches CRON

```bash
# Éditer le crontab pour www-data
sudo crontab -u www-data -e
```

```cron
# Collecte quotidienne à 2h du matin
0 2 * * * cd /var/www/entreprise-platform && /usr/bin/php backend/cron/daily_import.php >> storage/logs/cron.log 2>&1

# Nettoyage des logs (tous les dimanches à 4h)
0 4 * * 0 cd /var/www/entreprise-platform && /usr/bin/php backend/cron/cleanup.php >> storage/logs/cron.log 2>&1

# Sauvegarde de la base de données (tous les jours à 5h)
0 5 * * * /usr/bin/mysqldump -u entreprise_user -pVotreMotDePasseSecurise123! entreprise_data > /var/www/entreprise-platform/storage/backups/db_$(date +\%Y\%m\%d).sql
```

### 9. SSL/HTTPS avec Let's Encrypt (Recommandé)

```bash
# Installation Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtenir le certificat SSL
sudo certbot --nginx -d votre-domaine.com -d www.votre-domaine.com

# Renouvellement automatique
sudo crontab -e
# Ajouter : 0 3 * * * /usr/bin/certbot renew --quiet
```

### 10. Tests et vérifications

```bash
# Test PHP
php -v
sudo systemctl status php8.2-fpm

# Test MySQL
mysql -u entreprise_user -p -e "SELECT COUNT(*) FROM entreprise_data.companies;"

# Test Nginx
sudo systemctl status nginx
curl -I http://votre-domaine.com

# Test API
curl http://votre-domaine.com/backend/api/stats.php

# Test cron
sudo -u www-data php /var/www/entreprise-platform/backend/cron/daily_import.php --test
```

### 11. Monitoring et logs

```bash
# Voir les logs en temps réel
sudo tail -f /var/log/nginx/entreprise-platform-access.log
sudo tail -f /var/log/nginx/entreprise-platform-error.log
sudo tail -f /var/www/entreprise-platform/storage/logs/collector_$(date +%Y-%m-%d).log

# Surveillance des processus
sudo systemctl status mysql php8.2-fpm nginx

# Espace disque
df -h
du -sh /var/www/entreprise-platform/storage/documents/
```

### 12. Sécurisation (Recommandée)

```bash
# Firewall UFW
sudo ufw allow 22/tcp  # SSH
sudo ufw allow 80/tcp  # HTTP
sudo ufw allow 443/tcp # HTTPS
sudo ufw enable

# Fail2ban pour protection SSH
sudo apt install -y fail2ban
sudo systemctl enable fail2ban

# Mise à jour automatique des paquets sécurité
sudo apt install -y unattended-upgrades
echo 'Unattended-Upgrade::Automatic-Reboot "false";' | sudo tee -a /etc/apt/apt.conf.d/50unattended-upgrades
```

## 🚀 Démarrage rapide

Une fois tout installé :

```bash
# 1. Vérifier que tous les services sont actifs
sudo systemctl status mysql php8.2-fpm nginx

# 2. Tester l'accès web
curl -I http://votre-domaine.com

# 3. Tester l'API
curl http://votre-domaine.com/backend/api/stats.php

# 4. Premier import de données (optionnel)
sudo -u www-data php /var/www/entreprise-platform/backend/cron/daily_import.php
```

## 🎯 Accès à la plateforme

- **Interface web** : http://votre-domaine.com
- **API** : http://votre-domaine.com/backend/api/
- **Logs** : /var/www/entreprise-platform/storage/logs/
- **Documents** : /var/www/entreprise-platform/storage/documents/

## 🛠️ Maintenance courante

```bash
# Mise à jour du code (si Git)
cd /var/www/entreprise-platform
sudo -u www-data git pull origin main

# Nettoyage manuel
sudo -u www-data php backend/cron/cleanup.php

# Sauvegarde manuelle
mysqldump -u entreprise_user -p entreprise_data > backup_$(date +%Y%m%d).sql

# Redémarrer les services après modification
sudo systemctl restart php8.2-fpm nginx
```

## ⚠️ Dépannage

### Erreurs communes

**Erreur 502 Bad Gateway**
```bash
sudo systemctl status php8.2-fpm
sudo systemctl restart php8.2-fpm
```

**Erreur de connexion base de données**
```bash
mysql -u entreprise_user -p entreprise_data
# Vérifier les identifiants dans .env
```

**Permissions insuffisantes**
```bash
sudo chown -R www-data:www-data /var/www/entreprise-platform
sudo chmod -R 777 /var/www/entreprise-platform/storage
```

Cette installation manuelle vous donne un contrôle total sur chaque composant et est parfaite pour les environnements de production personnalisés !
