# Documentation API - Entreprise Data Platform

## Vue d'ensemble

L'API REST permet d'accéder programmatiquement aux données d'entreprises françaises.

**Base URL:** `http://votre-domaine.com/backend/api/`

## Authentification

Actuellement, l'API est publique. Une authentification par token sera ajoutée dans les versions futures.

## Endpoints

### 1. Recherche d'entreprises

**GET** `/backend/api/search.php`

Recherche d'entreprises par nom, SIREN ou autres critères.

#### Paramètres
- `q` (string, requis) : Terme de recherche
- `limit` (int, optionnel) : Nombre de résultats (max 100, défaut 20)
- `offset` (int, optionnel) : Décalage pour pagination (défaut 0)
- `format` (string, optionnel) : `full` ou `summary` (défaut full)

#### Exemple
```bash
curl "http://localhost/backend/api/search.php?q=Google&limit=5&format=summary"
```

#### Réponse
```json
{
  "success": true,
  "query": "Google",
  "total_found": 1,
  "limit": 5,
  "offset": 0,
  "results": [
    {
      "siren": "552120222",
      "denomination": "GOOGLE FRANCE",
      "forme_juridique": "SAS",
      "ville": "PARIS",
      "statut": "ACTIF",
      "date_creation": "2002-11-18"
    }
  ],
  "timestamp": "2025-09-27T11:00:00+02:00"
}
```

### 2. Détails d'une entreprise

**GET** `/backend/api/companies.php`

Récupère les informations complètes d'une entreprise.

#### Paramètres
- `siren` (string) : SIREN de l'entreprise
- `id` (int) : ID interne de l'entreprise
- `include` (string, optionnel) : `all`, `basic`, `documents`, `finance`, `dirigeants`

#### Exemple
```bash
curl "http://localhost/backend/api/companies.php?siren=552120222&include=all"
```

### 3. Statistiques

**GET** `/backend/api/stats.php`

Récupère les statistiques générales de la plateforme.

#### Exemple
```bash
curl "http://localhost/backend/api/stats.php"
```

#### Réponse
```json
{
  "success": true,
  "companies": {
    "total": 150000,
    "active": 145000,
    "closed": 5000,
    "synchronized": 100000,
    "quality_score_avg": 75.5
  },
  "documents": [
    {
      "type_document": "BILAN",
      "nb_documents": 50000,
      "nb_telecharges": 45000,
      "nb_disponibles": 50000
    }
  ],
  "timestamp": "2025-09-27T11:00:00+02:00"
}
```

## Codes d'erreur

- `200` : Succès
- `400` : Paramètres manquants ou invalides
- `404` : Ressource non trouvée
- `429` : Trop de requêtes (rate limiting)
- `500` : Erreur interne du serveur

## Rate Limiting

- 100 requêtes par heure par IP
- Headers de réponse : `X-RateLimit-Limit`, `X-RateLimit-Remaining`

## Exemples d'intégration

### PHP
```php
<?php
$api_url = "http://localhost/backend/api/search.php";
$params = http_build_query(['q' => 'Google', 'format' => 'summary']);

$response = file_get_contents($api_url . '?' . $params);
$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['results'] as $company) {
        echo $company['denomination'] . " (" . $company['siren'] . ")\n";
    }
}
?>
```

### JavaScript
```javascript
fetch('/backend/api/search.php?q=Google&format=summary')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      data.results.forEach(company => {
        console.log(`${company.denomination} (${company.siren})`);
      });
    }
  });
```

### Python
```python
import requests

response = requests.get('http://localhost/backend/api/search.php', {
    'q': 'Google',
    'format': 'summary'
})

data = response.json()
if data['success']:
    for company in data['results']:
        print(f"{company['denomination']} ({company['siren']})")
```
