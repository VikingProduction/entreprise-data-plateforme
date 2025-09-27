<?php
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/Company.php';

$siren = $_GET['siren'] ?? '';
if (empty($siren)) {
    header('Location: /');
    exit;
}

$company = new Company();
$companyData = $company->findBySiren($siren);

if (!$companyData) {
    http_response_code(404);
    echo "Entreprise non trouvée";
    exit;
}

// Charger toutes les relations
$companyData = $company->getCompanyWithRelations($companyData['id']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($companyData['denomination']) ?> - SIREN <?= $companyData['siren'] ?></title>
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
                <a class="nav-link" href="/">Nouvelle recherche</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- En-tête entreprise -->
        <div class="company-header-full">
            <div class="row">
                <div class="col-md-8">
                    <h1><?= htmlspecialchars($companyData['denomination']) ?></h1>
                    <p class="text-muted mb-2">
                        <strong>SIREN:</strong> <?= $companyData['siren'] ?>
                        <?php if ($companyData['siret_siege']): ?>
                            | <strong>SIRET:</strong> <?= $companyData['siret_siege'] ?>
                        <?php endif; ?>
                    </p>
                    <div class="d-flex gap-2 mb-3">
                        <?php
                        $statusClass = $companyData['statut'] === 'ACTIF' ? 'bg-success' : 'bg-danger';
                        ?>
                        <span class="badge <?= $statusClass ?>"><?= $companyData['statut'] ?></span>
                        <span class="badge bg-secondary"><?= htmlspecialchars($companyData['forme_juridique']) ?></span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                        <button class="btn btn-outline-success" onclick="exportPDF()">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglets -->
        <ul class="nav nav-tabs" id="companyTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                    <i class="fas fa-info-circle"></i> Informations
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dirigeants-tab" data-bs-toggle="tab" data-bs-target="#dirigeants" type="button" role="tab">
                    <i class="fas fa-users"></i> Dirigeants (<?= count($companyData['dirigeants'] ?? []) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                    <i class="fas fa-file-alt"></i> Documents (<?= count($companyData['documents'] ?? []) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="finance-tab" data-bs-toggle="tab" data-bs-target="#finance" type="button" role="tab">
                    <i class="fas fa-chart-line"></i> Finances
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="jugements-tab" data-bs-toggle="tab" data-bs-target="#jugements" type="button" role="tab">
                    <i class="fas fa-gavel"></i> Jugements (<?= count($companyData['jugements'] ?? []) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="companyTabsContent">
            <!-- Onglet Informations -->
            <div class="tab-pane fade show active" id="info" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-building"></i> Identification</h5>
                        <div class="info-row">
                            <div class="info-label">Dénomination:</div>
                            <div class="info-value"><?= htmlspecialchars($companyData['denomination']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Forme juridique:</div>
                            <div class="info-value"><?= htmlspecialchars($companyData['forme_juridique']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Capital social:</div>
                            <div class="info-value">
                                <?= $companyData['capital_social'] ? number_format($companyData['capital_social'], 2, ',', ' ') . ' €' : 'N/A' ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date création:</div>
                            <div class="info-value">
                                <?= $companyData['date_creation'] ? date('d/m/Y', strtotime($companyData['date_creation'])) : 'N/A' ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-map-marker-alt"></i> Adresse</h5>
                        <div class="info-row">
                            <div class="info-label">Adresse:</div>
                            <div class="info-value">
                                <?= htmlspecialchars($companyData['adresse_ligne1']) ?><br>
                                <?= htmlspecialchars($companyData['code_postal'] . ' ' . $companyData['ville']) ?>
                            </div>
                        </div>

                        <h5 class="mt-4"><i class="fas fa-industry"></i> Activité</h5>
                        <div class="info-row">
                            <div class="info-label">Activité principale:</div>
                            <div class="info-value"><?= htmlspecialchars($companyData['activite_principale']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Code APE:</div>
                            <div class="info-value"><?= htmlspecialchars($companyData['code_ape']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Secteur:</div>
                            <div class="info-value"><?= htmlspecialchars($companyData['secteur_activite']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Dirigeants -->
            <div class="tab-pane fade" id="dirigeants" role="tabpanel">
                <?php if (!empty($companyData['dirigeants'])): ?>
                    <?php foreach ($companyData['dirigeants'] as $dirigeant): ?>
                        <div class="document-item">
                            <div class="document-title">
                                <?= htmlspecialchars($dirigeant['prenom'] . ' ' . $dirigeant['nom']) ?>
                                <span class="badge bg-primary ms-2"><?= htmlspecialchars($dirigeant['fonction']) ?></span>
                            </div>
                            <div class="document-meta">
                                <?php if ($dirigeant['date_debut']): ?>
                                    En fonction depuis le <?= date('d/m/Y', strtotime($dirigeant['date_debut'])) ?>
                                <?php endif; ?>
                                <?php if ($dirigeant['ville']): ?>
                                    | Domicilié à <?= htmlspecialchars($dirigeant['ville']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun dirigeant enregistré
                    </div>
                <?php endif; ?>
            </div>

            <!-- Onglet Documents -->
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <?php if (!empty($companyData['documents'])): ?>
                    <?php foreach ($companyData['documents'] as $document): ?>
                        <div class="document-item">
                            <div class="document-title">
                                <?= htmlspecialchars($document['titre'] ?: $document['type_document']) ?>
                                <?php if ($document['telecharge']): ?>
                                    <a href="download.php?id=<?= $document['id'] ?>" class="btn btn-sm btn-outline-success ms-2">
                                        <i class="fas fa-download"></i> Télécharger
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="document-meta">
                                Type: <?= htmlspecialchars($document['type_document']) ?>
                                <?php if ($document['date_document']): ?>
                                    | Date: <?= date('d/m/Y', strtotime($document['date_document'])) ?>
                                <?php endif; ?>
                                <?php if ($document['exercice_fin']): ?>
                                    | Exercice: <?= date('Y', strtotime($document['exercice_fin'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun document disponible
                    </div>
                <?php endif; ?>
            </div>

            <!-- Onglet Finances -->
            <div class="tab-pane fade" id="finance" role="tabpanel">
                <?php if (!empty($companyData['finance'])): ?>
                    <?php $finance = $companyData['finance']; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Exercice <?= date('Y', strtotime($finance['exercice_fin'])) ?></h5>
                            <div class="info-row">
                                <div class="info-label">Chiffre d'affaires:</div>
                                <div class="info-value">
                                    <?= $finance['chiffre_affaires'] ? number_format($finance['chiffre_affaires'], 0, ',', ' ') . ' €' : 'N/A' ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Résultat net:</div>
                                <div class="info-value">
                                    <?= $finance['resultat_net'] ? number_format($finance['resultat_net'], 0, ',', ' ') . ' €' : 'N/A' ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Effectif:</div>
                                <div class="info-value"><?= $finance['effectif_fin_exercice'] ?: 'N/A' ?></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucune donnée financière disponible
                    </div>
                <?php endif; ?>
            </div>

            <!-- Onglet Jugements -->
            <div class="tab-pane fade" id="jugements" role="tabpanel">
                <?php if (!empty($companyData['jugements'])): ?>
                    <?php foreach ($companyData['jugements'] as $jugement): ?>
                        <div class="document-item">
                            <div class="document-title">
                                <?= htmlspecialchars($jugement['type_jugement']) ?>
                                <span class="badge bg-warning ms-2"><?= htmlspecialchars($jugement['tribunal']) ?></span>
                            </div>
                            <div class="document-meta">
                                Date: <?= date('d/m/Y', strtotime($jugement['date_jugement'])) ?>
                                <?php if ($jugement['numero_rg']): ?>
                                    | RG: <?= htmlspecialchars($jugement['numero_rg']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($jugement['description']): ?>
                                <p class="mt-2"><?= htmlspecialchars($jugement['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Aucune procédure collective en cours
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportPDF() {
            // TODO: Implémenter export PDF
            alert('Fonctionnalité en cours de développement');
        }
    </script>
</body>
</html>
