# 🏢 Entreprise Data Platform

## Plateforme complète de données d'entreprises françaises

Une alternative open source à Pappers/Verif avec architecture web complète (PHP/MySQL/Nginx) pour collecter, stocker et exploiter les données officielles d'entreprises françaises.

![Version](https://img.shields.io/badge/version-1.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1.svg)
![Nginx](https://img.shields.io/badge/Nginx-1.24-009639.svg)

## 🎯 Fonctionnalités

### 🔍 Frontend Web (Style Pappers)
- **Interface de recherche** intuitive et responsive
- **Fiche entreprise complète** avec onglets (infos, dirigeants, documents, finances, jugements)
- **Recherche en temps réel** avec autocomplétion
- **Export PDF** des fiches entreprises
- **Design moderne** Bootstrap 5 + CSS personnalisé

### 📊 Sources de données intégrées
- **INPI** : Créations, modifications, bilans, actes depuis 1993
- **BODACC** : Jugements et procédures collectives 
- **INSEE Sirene** : Données administratives officielles
- **AFNIC/RDAP** : Informations noms de domaine .fr

### 🚀 Backend PHP robuste
- **Architecture MVC** avec modèles Database/Company/Document
- **API REST** complète (recherche, entreprises, stats)
- **Collecteurs automatisés** INPI/BODACC avec gestion d'erreurs
- **Cache intelligent** des recherches fréquentes
- **Rate limiting** et sécurité

### 🗄️ Base de données MySQL optimisée
- **11 tables** structurées (entreprises, dirigeants, documents, jugements...)
- **Index optimisés** pour recherche full-text et performances
- **Scores de qualité** automatiques des données
- **Historique complet** avec versionning

## 📁 Structure du projet

```
entreprise-data-platform/
├── backend/
│   ├── api/          # API REST (search, companies, stats)
│   ├── collectors/   # Collecteurs INPI/BODACC/Sirene  
│   ├── models/       # Modèles Database/Company/Document
│   ├── config/       # Configuration base/API keys
│   └── cron/         # Scripts automatisés
├── frontend/
│   ├── assets/       # CSS/JS/Images
│   ├── pages/        # Pages web (search, company)
│   └── index.php     # Page d'accueil
├── database/
│   └── schema.sql    # Schéma MySQL complet
├── config/
│   ├── nginx.conf    # Configuration Nginx
│   └── .env.example  # Variables d'environnement
└── docker/
    ├── docker-compose.yml
    └── Dockerfile
```

## ⚡ Installation rapide

### Option 1: Docker (Recommandé)

```bash
# 1. Cloner le projet
git clone https://github.com/VikingProduction/entreprise-data-plateforme
cd entreprise-data-platform

# 2. Configurer
cp .env.example .env
# Remplir les identifiants INPI dans .env

# 3. Installer
chmod +x install.sh
./install.sh

# 4. Accéder
# Web: http://localhost
# API: http://localhost/api/
# PhpMyAdmin: http://localhost:8080
```

### Option 2: Installation manuelle

```bash
# Prérequis: PHP 8.2, MySQL 8.0, Nginx

# 1. Base de données
mysql -u root -p < database/schema.sql

# 2. Configuration web
cp config/nginx.conf /etc/nginx/sites-available/entreprise-platform
ln -s /etc/nginx/sites-available/entreprise-platform /etc/nginx/sites-enabled/
systemctl reload nginx

# 3. Permissions
chmod -R 755 .
chmod -R 777 storage/

# 4. Cron jobs
crontab crontab
```

## 🔧 Configuration

### Identifiants API obligatoires

1. **INPI** : Créer un compte sur [data.inpi.fr](https://data.inpi.fr)
   - Demander l'accès aux API "Actes" et "Comptes annuels"
   - Remplir `INPI_USERNAME` et `INPI_PASSWORD` dans `.env`

2. **Pappers** (optionnel) : Token gratuit sur [pappers.fr](https://www.pappers.fr/api)

### Variables d'environnement (.env)

```env
# Base de données
DB_HOST=localhost
DB_NAME=entreprise_data  
DB_USER=root
DB_PASS=your_password

# API INPI (obligatoire)
INPI_USERNAME=votre_email@domain.com
INPI_PASSWORD=votre_mot_de_passe

# Options
DOWNLOAD_DOCUMENTS=true
MAX_SEARCH_RESULTS=100
```

## 📈 Utilisation

### Interface Web

1. **Recherche** : Page d'accueil avec moteur de recherche
2. **Résultats** : Liste paginée avec tri par pertinence  
3. **Fiche entreprise** : Vue complète multi-onglets
4. **Export** : PDF, API, données brutes

### API REST

```bash
# Recherche
curl "http://localhost/api/search.php?q=Google&format=summary"

# Entreprise complète  
curl "http://localhost/api/companies.php?siren=552120222&include=all"

# Statistiques
curl "http://localhost/api/stats.php"
```

### Collecte de données

```bash
# Import manuel
php backend/collectors/inpi_import.php --siren=552120222

# Import en masse
php backend/cron/daily_import.php

# Surveillance automatique (cron)
0 2 * * * cd /var/www/html && php backend/cron/daily_import.php
```

## 🎨 Captures d'écran

### Page d'accueil
Interface de recherche moderne avec suggestions et exemples.

### Fiche entreprise
Vue complète avec onglets : informations, dirigeants, documents PDF, données financières, jugements.

### Résultats de recherche  
Liste responsive avec statuts, formes juridiques et métadonnées.

## 🔐 Sécurité

- **Rate limiting** : 100 req/h par IP
- **Validation d'entrées** : Protection SQL injection/XSS
- **Headers sécurisés** : CORS, CSP configurés  
- **Accès fichiers** : Documents PDF protégés
- **Logs complets** : Traçabilité des actions

## 📊 Performance

- **Cache Redis** : Recherches fréquentes (1h TTL)
- **Index MySQL** : Full-text search optimisé
- **Pagination** : Résultats limités (max 100)
- **Compression** : Gzip activé (nginx)
- **CDN ready** : Assets optimisés

## 🛠️ Développement

### API personnalisée

```php
// Nouveau endpoint API
class CustomAPI {
    public function getCompanyStats($siren) {
        $company = new Company();
        return $company->getDetailedStats($siren);
    }
}
```

### Nouveau collecteur

```php
// Collecteur personnalisé
class MyCollector extends BaseCollector {
    public function collect() {
        // Votre logique de collecte
    }
}
```

### Frontend personnalisé

Le frontend utilise Bootstrap 5 + CSS/JS vanilla. Facilement extensible pour frameworks modernes (Vue.js, React).

## 📋 Roadmap

### Version 1.1
- [ ] Authentification utilisateurs
- [ ] Dashboard analytics avancé  
- [ ] Export Excel/CSV avancé
- [ ] API GraphQL

### Version 1.2
- [ ] Machine Learning (scoring)
- [ ] Alertes temps réel
- [ ] Intégration Webhook
- [ ] Mode SaaS multi-tenant

### Version 2.0
- [ ] Frontend React/Vue.js
- [ ] API v2 avec pagination cursor
- [ ] Elasticsearch pour la recherche
- [ ] Microservices architecture

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/amazing-feature`)
3. Commit (`git commit -m 'Add amazing feature'`)
4. Push (`git push origin feature/amazing-feature`)
5. Ouvrir une Pull Request

## 📄 Licence

Projet sous licence MIT - voir [LICENSE](LICENSE) pour détails.

## 🆘 Support

- **Documentation** : `/docs/`
- **Issues** : GitHub Issues
- **Wiki** : GitHub Wiki
- **Discussions** : GitHub Discussions

## 🙏 Remerciements

- **INPI** pour l'API des données d'entreprises
- **INSEE** pour l'API Sirene  
- **DILA** pour les données BODACC
- **Communauté open source** PHP/MySQL

---

**⭐ Si ce projet vous est utile, n'hésitez pas à laisser une étoile !**

Made with ❤️ in France
