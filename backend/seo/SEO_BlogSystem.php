<?php
/**
 * Système de blog SEO automatisé
 * Génération de contenu pour améliorer le référencement
 */

class SEOBlogSystem {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Générateur d'articles de blog SEO automatiques
     */
    public function generateSEOArticles() {
        $templates = [
            'company_analysis' => [
                'title_template' => 'Analyse complète de {company_name} : Dirigeants, Finances et Perspectives 2025',
                'meta_description' => 'Découvrez tout sur {company_name} : dirigeants, bilans, évolution financière. Données officielles INPI mises à jour.',
                'keywords' => ['{company_name}', 'analyse entreprise', 'bilans', 'dirigeants', 'SIREN {siren}']
            ],
            'sector_analysis' => [
                'title_template' => 'Secteur {sector_name} en 2025 : Top entreprises, chiffres clés et tendances',
                'meta_description' => 'Analyse du secteur {sector_name} : leaders du marché, performances financières, opportunités d'investissement.',
                'keywords' => ['{sector_name}', 'secteur activité', 'entreprises leader', 'analyse marché']
            ],
            'regional_focus' => [
                'title_template' => 'Entreprises à {city_name} : Guide complet des leaders locaux en 2025',
                'meta_description' => 'Découvrez les principales entreprises de {city_name} : secteurs porteurs, emploi, opportunités business.',
                'keywords' => ['{city_name}', 'entreprises locales', 'économie régionale', 'business local']
            ]
        ];

        // Générer des articles basés sur les recherches populaires
        $popularSearches = $this->getPopularSearchQueries();

        foreach ($popularSearches as $search) {
            $this->createArticleFromSearch($search, $templates);
        }
    }

    /**
     * Créer un article depuis une recherche populaire
     */
    private function createArticleFromSearch($searchData, $templates) {
        if ($this->isCompanySearch($searchData['query'])) {
            $this->createCompanyAnalysisArticle($searchData, $templates['company_analysis']);
        } elseif ($this->isSectorSearch($searchData['query'])) {
            $this->createSectorAnalysisArticle($searchData, $templates['sector_analysis']);
        } elseif ($this->isCitySearch($searchData['query'])) {
            $this->createRegionalFocusArticle($searchData, $templates['regional_focus']);
        }
    }

    /**
     * Créer un article d'analyse d'entreprise
     */
    private function createCompanyAnalysisArticle($searchData, $template) {
        $company = $this->getCompanyData($searchData['query']);
        if (!$company) return;

        $variables = [
            '{company_name}' => $company['denomination'],
            '{siren}' => $company['siren'],
            '{city}' => $company['ville'],
            '{sector}' => $company['secteur_activite']
        ];

        $title = str_replace(array_keys($variables), array_values($variables), $template['title_template']);
        $metaDescription = str_replace(array_keys($variables), array_values($variables), $template['meta_description']);

        $content = $this->generateCompanyArticleContent($company);
        $slug = $this->createSEOSlug($title);

        $this->saveBlogPost([
            'title' => $title,
            'slug' => $slug,
            'meta_description' => $metaDescription,
            'content' => $content,
            'keywords' => $this->replaceKeywordVariables($template['keywords'], $variables),
            'category' => 'analyse-entreprise',
            'status' => 'published'
        ]);
    }

    /**
     * Générer le contenu d'un article d'entreprise
     */
    private function generateCompanyArticleContent($company) {
        $content = "# Analyse de {$company['denomination']} : Portrait d'entreprise complet

";

        // Introduction SEO
        $content .= "## Introduction

";
        $content .= "{$company['denomination']} (SIREN {$company['siren']}) est une entreprise {$company['forme_juridique']} ";
        $content .= "basée à {$company['ville']}, active dans le secteur {$company['secteur_activite']}. ";
        $content .= "Créée le " . date('d/m/Y', strtotime($company['date_creation'])) . ", ";
        $content .= "cette analyse complète vous présente ses dirigeants, sa situation financière et ses perspectives.

";

        // Informations générales
        $content .= "## Informations générales de l'entreprise

";
        $content .= "- **Dénomination sociale** : {$company['denomination']}
";
        $content .= "- **SIREN** : {$company['siren']}
";
        $content .= "- **Forme juridique** : {$company['forme_juridique']}
";
        $content .= "- **Date de création** : " . date('d/m/Y', strtotime($company['date_creation'])) . "
";
        $content .= "- **Siège social** : {$company['adresse_ligne1']}, {$company['code_postal']} {$company['ville']}
";
        if ($company['capital_social']) {
            $content .= "- **Capital social** : " . number_format($company['capital_social'], 0, ',', ' ') . " €
";
        }
        $content .= "
";

        // Activité
        $content .= "## Secteur d'activité et positionnement

";
        $content .= "{$company['denomination']} exerce son activité principale dans le domaine : {$company['activite_principale']} ";
        $content .= "(code APE {$company['code_ape']}).

";

        // Dirigeants
        if (!empty($company['dirigeants'])) {
            $content .= "## Équipe dirigeante

";
            foreach ($company['dirigeants'] as $dirigeant) {
                $content .= "### {$dirigeant['prenom']} {$dirigeant['nom']} - {$dirigeant['fonction']}

";
                if ($dirigeant['date_debut']) {
                    $content .= "En fonction depuis le " . date('d/m/Y', strtotime($dirigeant['date_debut'])) . ".

";
                }
            }
        }

        // Données financières
        if (!empty($company['finance'])) {
            $finance = $company['finance'];
            $content .= "## Situation financière

";
            $content .= "### Exercice " . date('Y', strtotime($finance['exercice_fin'])) . "

";

            if ($finance['chiffre_affaires']) {
                $content .= "- **Chiffre d'affaires** : " . number_format($finance['chiffre_affaires'], 0, ',', ' ') . " €
";
            }
            if ($finance['resultat_net']) {
                $content .= "- **Résultat net** : " . number_format($finance['resultat_net'], 0, ',', ' ') . " €
";
            }
            if ($finance['effectif_fin_exercice']) {
                $content .= "- **Effectif** : {$finance['effectif_fin_exercice']} salariés
";
            }
            $content .= "
";
        }

        // Documents disponibles
        if (!empty($company['documents'])) {
            $content .= "## Documents officiels disponibles

";
            $content .= "Les derniers documents déposés par {$company['denomination']} incluent :

";
            foreach (array_slice($company['documents'], 0, 5) as $doc) {
                $content .= "- {$doc['type_document']}";
                if ($doc['date_document']) {
                    $content .= " du " . date('d/m/Y', strtotime($doc['date_document']));
                }
                $content .= "
";
            }
            $content .= "
";
        }

        // Conclusion SEO
        $content .= "## Conclusion

";
        $content .= "{$company['denomination']} présente un profil d'entreprise ";
        $content .= $company['statut'] === 'ACTIF' ? 'actif' : 'en difficulté';
        $content .= " dans le secteur {$company['secteur_activite']}. ";
        $content .= "Cette analyse basée sur les données officielles INPI vous donne une vision complète ";
        $content .= "de sa structure, ses dirigeants et sa situation financière.

";

        // CTA
        $content .= "### Surveillez cette entreprise

";
        $content .= "Restez informé des changements de {$company['denomination']} grâce à notre service ";
        $content .= "de surveillance automatique : nouveaux dirigeants, bilans, jugements.

";
        $content .= "[Créer une surveillance gratuite](/surveillance/create?siren={$company['siren']})

";

        return $content;
    }

    /**
     * Optimisations SEO techniques
     */
    public function generateSitemapXML() {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "
";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "
";

        // Pages principales
        $mainPages = [
            ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => '/pricing', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['url' => '/api', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['url' => '/blog', 'priority' => '0.8', 'changefreq' => 'daily']
        ];

        foreach ($mainPages as $page) {
            $sitemap .= "  <url>
";
            $sitemap .= "    <loc>" . BASE_URL . $page['url'] . "</loc>
";
            $sitemap .= "    <lastmod>" . date('Y-m-d') . "</lastmod>
";
            $sitemap .= "    <changefreq>{$page['changefreq']}</changefreq>
";
            $sitemap .= "    <priority>{$page['priority']}</priority>
";
            $sitemap .= "  </url>
";
        }

        // Articles de blog
        $articles = $this->getPublishedArticles();
        foreach ($articles as $article) {
            $sitemap .= "  <url>
";
            $sitemap .= "    <loc>" . BASE_URL . "/blog/{$article['slug']}</loc>
";
            $sitemap .= "    <lastmod>{$article['updated_at']}</lastmod>
";
            $sitemap .= "    <changefreq>monthly</changefreq>
";
            $sitemap .= "    <priority>0.7</priority>
";
            $sitemap .= "  </url>
";
        }

        // Pages entreprises populaires
        $popularCompanies = $this->getPopularCompanies(1000);
        foreach ($popularCompanies as $company) {
            $sitemap .= "  <url>
";
            $sitemap .= "    <loc>" . BASE_URL . "/entreprise/{$company['siren']}</loc>
";
            $sitemap .= "    <lastmod>{$company['updated_at']}</lastmod>
";
            $sitemap .= "    <changefreq>weekly</changefreq>
";
            $sitemap .= "    <priority>0.6</priority>
";
            $sitemap .= "  </url>
";
        }

        $sitemap .= '</urlset>';

        file_put_contents(BASE_PATH . '/public/sitemap.xml', $sitemap);
    }

    /**
     * Génération de pages landing SEO
     */
    public function generateSEOLandingPages() {
        // Pages par secteur d'activité
        $sectors = $this->getTopSectors();
        foreach ($sectors as $sector) {
            $this->generateSectorLandingPage($sector);
        }

        // Pages par ville
        $cities = $this->getTopCities();
        foreach ($cities as $city) {
            $this->generateCityLandingPage($city);
        }

        // Pages par forme juridique
        $formes = ['SAS', 'SARL', 'SA', 'EURL', 'SCI'];
        foreach ($formes as $forme) {
            $this->generateFormeJuridiqueLandingPage($forme);
        }
    }

    private function generateSectorLandingPage($sector) {
        $companies = $this->getCompaniesBySector($sector['secteur'], 50);
        $stats = $this->getSectorStats($sector['secteur']);

        $content = "# Entreprises du secteur {$sector['secteur']} en France

";
        $content .= "Découvrez les {$stats['total']} entreprises françaises actives dans le secteur {$sector['secteur']}. ";
        $content .= "Données officielles, dirigeants, bilans et surveillance automatique.

";

        $content .= "## Statistiques du secteur

";
        $content .= "- **Nombre d'entreprises** : " . number_format($stats['total']) . "
";
        $content .= "- **CA moyen** : " . number_format($stats['ca_moyen']) . " €
";
        $content .= "- **Effectif moyen** : {$stats['effectif_moyen']} salariés

";

        $content .= "## Top entreprises du secteur

";
        foreach ($companies as $company) {
            $content .= "### [{$company['denomination']}](/entreprise/{$company['siren']})
";
            $content .= "- **Ville** : {$company['ville']}
";
            $content .= "- **Forme** : {$company['forme_juridique']}
";
            if ($company['ca_2023']) {
                $content .= "- **CA 2023** : " . number_format($company['ca_2023']) . " €
";
            }
            $content .= "
";
        }

        $this->saveLandingPage('secteur-' . $this->slugify($sector['secteur']), $content, [
            'title' => "Entreprises {$sector['secteur']} | Annuaire et données officielles",
            'description' => "Liste complète des entreprises du secteur {$sector['secteur']} en France. Bilans, dirigeants, surveillance gratuite."
        ]);
    }
}
