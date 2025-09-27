// JavaScript pour la recherche d'entreprises

document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const resultsContainer = document.getElementById('resultsContainer');
    const loadingSpinner = document.getElementById('loadingSpinner');

    // Exemples de recherche
    document.querySelectorAll('.search-example').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const query = this.dataset.query;
            searchInput.value = query;
            performSearch(query);
        });
    });

    // Soumission du formulaire
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const query = searchInput.value.trim();
        if (query) {
            performSearch(query);
        }
    });

    // Recherche en temps réel (avec debounce)
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length >= 3) {
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 500);
        } else if (query.length === 0) {
            hideResults();
        }
    });

    function performSearch(query) {
        showLoading();

        fetch(`backend/api/search.php?q=${encodeURIComponent(query)}&format=summary&limit=20`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    displayResults(data.results, data.query);
                } else {
                    showError(data.message || 'Erreur lors de la recherche');
                }
            })
            .catch(error => {
                hideLoading();
                showError('Erreur de connexion');
                console.error('Search error:', error);
            });
    }

    function displayResults(results, query) {
        if (results.length === 0) {
            resultsContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Aucun résultat trouvé pour "${query}"
                </div>
            `;
        } else {
            let html = `
                <h3>Résultats pour "${query}" (${results.length})</h3>
                <div class="results-list">
            `;

            results.forEach(company => {
                html += createCompanyCard(company);
            });

            html += '</div>';
            resultsContainer.innerHTML = html;
        }

        searchResults.style.display = 'block';

        // Smooth scroll vers les résultats
        searchResults.scrollIntoView({ behavior: 'smooth' });
    }

    function createCompanyCard(company) {
        const statusClass = company.statut === 'ACTIF' ? 'status-active' : 'status-closed';
        const statusText = company.statut === 'ACTIF' ? 'Active' : 'Fermée';

        return `
            <a href="company.php?siren=${company.siren}" class="company-card d-block">
                <div class="company-header">
                    <div class="flex-grow-1">
                        <h4 class="company-denomination">${company.denomination || 'N/A'}</h4>
                        <div class="company-siren">SIREN: ${company.siren}</div>
                    </div>
                    <div class="company-status">
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                </div>
                <div class="company-meta">
                    <span><i class="fas fa-building"></i> ${company.forme_juridique || 'N/A'}</span>
                    ${company.ville ? `<span class="ms-3"><i class="fas fa-map-marker-alt"></i> ${company.ville}</span>` : ''}
                    ${company.date_creation ? `<span class="ms-3"><i class="fas fa-calendar"></i> Créée le ${formatDate(company.date_creation)}</span>` : ''}
                </div>
            </a>
        `;
    }

    function showLoading() {
        loadingSpinner.style.display = 'block';
        searchResults.style.display = 'none';
    }

    function hideLoading() {
        loadingSpinner.style.display = 'none';
    }

    function hideResults() {
        searchResults.style.display = 'none';
    }

    function showError(message) {
        resultsContainer.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                ${message}
            </div>
        `;
        searchResults.style.display = 'block';
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR');
    }
});
