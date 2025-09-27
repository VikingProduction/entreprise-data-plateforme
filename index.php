<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entreprise Data Platform - Recherche d'entreprises françaises</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="frontend/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="fas fa-building"></i> Entreprise Data Platform
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/stats.php">Statistiques</a>
                <a class="nav-link" href="/api-docs.html">API</a>
            </div>
        </div>
    </nav>

    <div class="hero-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="hero-content text-center">
                        <h1 class="display-4 fw-bold mb-4">
                            Recherchez toutes les entreprises françaises
                        </h1>
                        <p class="lead mb-5">
                            Accédez aux données officielles : bilans, dirigeants, jugements et plus encore
                        </p>

                        <div class="search-box">
                            <form id="searchForm" class="d-flex gap-2">
                                <div class="input-group input-group-lg flex-grow-1">
                                    <input type="text" 
                                           id="searchInput" 
                                           class="form-control" 
                                           placeholder="Nom d'entreprise, SIREN, dirigeant..."
                                           autocomplete="off">
                                    <button class="btn btn-success" type="submit">
                                        <i class="fas fa-search"></i> Rechercher
                                    </button>
                                </div>
                            </form>

                            <div class="search-examples mt-3">
                                <small class="text-muted">
                                    Exemples : 
                                    <a href="#" class="search-example" data-query="Google France">Google France</a>,
                                    <a href="#" class="search-example" data-query="552120222">552120222</a>,
                                    <a href="#" class="search-example" data-query="Microsoft">Microsoft</a>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="features-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-database fa-3x text-primary"></i>
                        </div>
                        <h3>Données officielles</h3>
                        <p>Informations issues de l'INPI, INSEE et BODACC</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-file-pdf fa-3x text-success"></i>
                        </div>
                        <h3>Documents complets</h3>
                        <p>Bilans, comptes annuels, statuts et actes</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon">
                            <i class="fas fa-gavel fa-3x text-warning"></i>
                        </div>
                        <h3>Veille juridique</h3>
                        <p>Jugements et procédures collectives</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Résultats de recherche -->
    <div id="searchResults" class="container my-5" style="display: none;">
        <div class="row">
            <div class="col-12">
                <div id="resultsContainer"></div>
            </div>
        </div>
    </div>

    <!-- Loading spinner -->
    <div id="loadingSpinner" class="text-center my-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Recherche en cours...</span>
        </div>
        <p class="mt-2">Recherche en cours...</p>
    </div>

    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; 2025 Entreprise Data Platform. Données sous licence ouverte.</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="/mentions-legales.html">Mentions légales</a> |
                    <a href="/api-docs.html">Documentation API</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="frontend/assets/js/search.js"></script>
</body>
</html>
