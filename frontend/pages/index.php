<!DOCTYPE html>
<html lang="fr" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <title>Recherche d'Entreprises Françaises | Alternative Pappers Gratuite</title>
    <meta name="description" content="Recherchez toutes les entreprises françaises gratuitement. Données INPI, bilans, dirigeants, jugements. Alternative à Pappers avec surveillance automatique et API.">
    <meta name="keywords" content="recherche entreprise, SIREN, SIRET, bilans, dirigeants, surveillance entreprise, alternative pappers, données INPI, API entreprises">
    <meta name="author" content="Entreprise Data Platform">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= BASE_URL ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="Recherche d'Entreprises Françaises | Données Officielles Gratuites">
    <meta property="og:description" content="Accédez gratuitement aux données de 30 millions d'entreprises françaises. Bilans, dirigeants, surveillance, API.">
    <meta property="og:image" content="<?= BASE_URL ?>/assets/images/og-image.jpg">
    <meta property="og:url" content="<?= BASE_URL ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Entreprise Data Platform">
    <meta property="og:locale" content="fr_FR">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Recherche d'Entreprises Françaises Gratuite">
    <meta name="twitter:description" content="30M d'entreprises, données INPI, surveillance automatique. Essai gratuit 14 jours.">
    <meta name="twitter:image" content="<?= BASE_URL ?>/assets/images/twitter-card.jpg">

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Entreprise Data Platform",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Any",
        "description": "Plateforme de recherche et surveillance d'entreprises françaises avec données officielles INPI",
        "url": "<?= BASE_URL ?>",
        "provider": {
            "@type": "Organization",
            "name": "Viking Production",
            "url": "<?= BASE_URL ?>"
        },
        "offers": [
            {
                "@type": "Offer",
                "name": "Plan Gratuit",
                "price": "0",
                "priceCurrency": "EUR",
                "description": "10 recherches par jour"
            },
            {
                "@type": "Offer",
                "name": "Plan Starter",
                "price": "29",
                "priceCurrency": "EUR",
                "description": "100 recherches par jour + surveillance"
            }
        ],
        "featureList": [
            "Recherche d'entreprises",
            "Données financières",
            "Surveillance automatique",
            "API REST",
            "Export PDF"
        ]
    }
    </script>

    <!-- Preload Critical Resources -->
    <link rel="preload" href="/assets/css/critical.css" as="style">
    <link rel="preload" href="/assets/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>

    <!-- CSS -->
    <style>
        /* Critical CSS inline */
        body { font-family: Inter, system-ui; margin: 0; line-height: 1.6; }
        .hero { background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%); color: white; padding: 4rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .search-box { max-width: 600px; margin: 0 auto; }
        .btn-primary { background: #0d6efd; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; }
        @media (max-width: 768px) { .hero { padding: 2rem 0; } }
    </style>

    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">

    <!-- Analytics -->
    <script defer data-domain="votre-domaine.com" src="https://plausible.io/js/script.js"></script>

    <?php if (ENVIRONMENT === 'production'): ?>
    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'GA_MEASUREMENT_ID');
    </script>
    <?php endif; ?>
</head>
<body>
    <!-- Skip Link for Accessibility -->
    <a href="#main-content" class="sr-only sr-only-focusable">Aller au contenu principal</a>

    <header role="banner">
        <nav class="navbar" aria-label="Navigation principale">
            <div class="container">
                <a class="navbar-brand" href="/" aria-label="Accueil Entreprise Data Platform">
                    <img src="/assets/images/logo.svg" alt="Logo" width="40" height="40">
                    Entreprise Data Platform
                </a>
                <ul class="navbar-nav" role="menubar">
                    <li role="none"><a href="/pricing" role="menuitem">Tarifs</a></li>
                    <li role="none"><a href="/api" role="menuitem">API</a></li>
                    <li role="none"><a href="/blog" role="menuitem">Blog</a></li>
                    <li role="none"><a href="/login" role="menuitem" class="btn-outline">Connexion</a></li>
                    <li role="none"><a href="/register" role="menuitem" class="btn-primary">Essai gratuit</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main id="main-content" role="main">
        <section class="hero" aria-labelledby="hero-title">
            <div class="container">
                <div class="hero-content">
                    <h1 id="hero-title">
                        Recherchez <strong>30 millions d'entreprises</strong> françaises
                    </h1>
                    <p class="lead">
                        Données officielles INPI • Surveillance automatique • API gratuite • Alternative à Pappers
                    </p>

                    <div class="search-box" role="search" aria-label="Recherche d'entreprises">
                        <form id="searchForm" action="/search" method="GET">
                            <div class="input-group">
                                <label for="searchInput" class="sr-only">Rechercher une entreprise</label>
                                <input 
                                    type="search" 
                                    id="searchInput"
                                    name="q"
                                    class="form-control" 
                                    placeholder="Nom d'entreprise, SIREN, dirigeant..."
                                    aria-describedby="search-help"
                                    autocomplete="off"
                                    required>
                                <button type="submit" class="btn btn-success" aria-label="Lancer la recherche">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                    </svg>
                                    Rechercher
                                </button>
                            </div>
                            <div id="search-help" class="search-examples">
                                Exemples : 
                                <button type="button" class="search-example" data-query="Google France">Google France</button>,
                                <button type="button" class="search-example" data-query="552120222">552120222</button>,
                                <button type="button" class="search-example" data-query="Microsoft">Microsoft</button>
                            </div>
                        </form>
                    </div>

                    <div class="trust-indicators">
                        <p><strong>+1M</strong> recherches par mois • <strong>50k+</strong> utilisateurs • Données <strong>INPI officielles</strong></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="features" aria-labelledby="features-title">
            <div class="container">
                <h2 id="features-title" class="section-title">Pourquoi choisir notre plateforme ?</h2>

                <div class="features-grid">
                    <article class="feature-card">
                        <div class="feature-icon" aria-hidden="true">📊</div>
                        <h3>Données 100% officielles</h3>
                        <p>Informations directement issues de l'INPI, INSEE et BODACC. Mise à jour quotidienne automatique.</p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon" aria-hidden="true">🔔</div>
                        <h3>Surveillance automatique</h3>
                        <p>Soyez alerté des changements : nouveaux dirigeants, jugements, bilans. Email + webhook.</p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon" aria-hidden="true">⚡</div>
                        <h3>API ultra-rapide</h3>
                        <p>Intégrez nos données dans vos applications. 99.9% uptime, documentation complète.</p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon" aria-hidden="true">💰</div>
                        <h3>Prix imbattables</h3>
                        <p>Jusqu'à 10x moins cher que Pappers. Plan gratuit généreux, pas de frais cachés.</p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon" aria-hidden="true">🏦</div>
                        <h3>Données financières</h3>
                        <p>Bilans complets, ratios financiers, évolution CA. Documents PDF téléchargeables.</p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon" aria-hidden="true">⚖️</div>
                        <h3>Veille juridique</h3>
                        <p>Procédures collectives, jugements tribunaux. Alertes en temps réel.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="comparison" aria-labelledby="comparison-title">
            <div class="container">
                <h2 id="comparison-title">Comparaison avec Pappers</h2>

                <div class="comparison-table">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Fonctionnalité</th>
                                <th scope="col">Notre plateforme</th>
                                <th scope="col">Pappers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row">Prix mensuel</th>
                                <td><strong>29€</strong></td>
                                <td>99€</td>
                            </tr>
                            <tr>
                                <th scope="row">Recherches/jour</th>
                                <td><strong>1000</strong></td>
                                <td>300</td>
                            </tr>
                            <tr>
                                <th scope="row">Surveillance</th>
                                <td><strong>50 entreprises</strong></td>
                                <td>10 entreprises</td>
                            </tr>
                            <tr>
                                <th scope="row">API calls/mois</th>
                                <td><strong>10,000</strong></td>
                                <td>1,000</td>
                            </tr>
                            <tr>
                                <th scope="row">Support</th>
                                <td><strong>Email + Chat</strong></td>
                                <td>Email uniquement</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cta-section">
                    <a href="/register" class="btn btn-primary btn-lg">Commencer gratuitement</a>
                    <p>Essai gratuit 14 jours • Sans engagement • Sans carte bancaire</p>
                </div>
            </div>
        </section>
    </main>

    <aside aria-labelledby="testimonials-title">
        <section class="testimonials">
            <div class="container">
                <h2 id="testimonials-title">Ce que disent nos utilisateurs</h2>
                <div class="testimonials-grid">
                    <blockquote class="testimonial">
                        <p>"Interface claire, données fiables, prix imbattable. Parfait pour notre cabinet comptable."</p>
                        <cite>— Marie D., Expert-comptable</cite>
                    </blockquote>
                    <blockquote class="testimonial">
                        <p>"L'API est excellente, documentation au top. Migration depuis Pappers sans souci."</p>
                        <cite>— Thomas R., Développeur</cite>
                    </blockquote>
                    <blockquote class="testimonial">
                        <p>"Surveillance automatique très pratique pour nos analyses de risque client."</p>
                        <cite>— Julie M., Analyste crédit</cite>
                    </blockquote>
                </div>
            </div>
        </section>
    </aside>

    <footer role="contentinfo">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Produit</h3>
                    <ul>
                        <li><a href="/features">Fonctionnalités</a></li>
                        <li><a href="/pricing">Tarifs</a></li>
                        <li><a href="/api">API</a></li>
                        <li><a href="/changelog">Nouveautés</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Ressources</h3>
                    <ul>
                        <li><a href="/blog">Blog</a></li>
                        <li><a href="/guides">Guides</a></li>
                        <li><a href="/help">Aide</a></li>
                        <li><a href="/status">Statut</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Entreprise</h3>
                    <ul>
                        <li><a href="/about">À propos</a></li>
                        <li><a href="/contact">Contact</a></li>
                        <li><a href="/careers">Carrières</a></li>
                        <li><a href="/press">Presse</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Légal</h3>
                    <ul>
                        <li><a href="/privacy">Confidentialité</a></li>
                        <li><a href="/terms">Conditions</a></li>
                        <li><a href="/cookies">Cookies</a></li>
                        <li><a href="/security">Sécurité</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2025 Entreprise Data Platform. Données sous licence ouverte française.</p>
                <div class="social-links">
                    <a href="https://twitter.com/entreprise-data" aria-label="Suivez-nous sur Twitter">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                        </svg>
                    </a>
                    <a href="https://linkedin.com/company/entreprise-data" aria-label="Suivez-nous sur LinkedIn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Critical JS inline
        document.addEventListener('DOMContentLoaded', function() {
            // Search examples
            document.querySelectorAll('.search-example').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('searchInput').value = this.dataset.query;
                    gtag && gtag('event', 'search_example_clicked', { query: this.dataset.query });
                });
            });

            // Form analytics
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                gtag && gtag('event', 'search_started', { query: document.getElementById('searchInput').value });
            });
        });
    </script>

    <!-- Load non-critical CSS -->
    <link rel="stylesheet" href="/assets/css/style.css" media="print" onload="this.media='all'">

    <!-- Service Worker for PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
</body>
</html>
