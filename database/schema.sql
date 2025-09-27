-- ============================================
-- Schéma de base de données MySQL
-- Plateforme de données d'entreprises 
-- Style Pappers/Verif
-- ============================================

SET foreign_key_checks = 0;
DROP DATABASE IF EXISTS entreprise_data;
CREATE DATABASE entreprise_data CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE entreprise_data;

-- ============================================
-- Table principale des entreprises
-- ============================================
CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    siren VARCHAR(9) UNIQUE NOT NULL,
    siret_siege VARCHAR(14),
    denomination VARCHAR(255),
    denomination_usuelle VARCHAR(255),
    nom_commercial VARCHAR(255),
    forme_juridique VARCHAR(100),
    forme_juridique_code VARCHAR(10),
    capital_social DECIMAL(15,2),
    date_creation DATE,
    date_immatriculation DATE,
    date_radiation DATE,
    activite_principale VARCHAR(255),
    code_ape VARCHAR(10),
    secteur_activite VARCHAR(100),

    -- Adresse siège social
    adresse_ligne1 VARCHAR(255),
    adresse_ligne2 VARCHAR(255),
    code_postal VARCHAR(10),
    ville VARCHAR(100),
    cedex VARCHAR(50),
    pays VARCHAR(50) DEFAULT 'FRANCE',

    -- Données financières
    ca_2023 DECIMAL(15,2),
    ca_2022 DECIMAL(15,2),
    ca_2021 DECIMAL(15,2),
    effectif_2023 INT,
    effectif_2022 INT,
    effectif_2021 INT,

    -- Statut et état
    statut ENUM('ACTIF', 'CESSE', 'RADIATION') DEFAULT 'ACTIF',
    etat_administratif ENUM('A','C') DEFAULT 'A',
    economie_sociale_solidaire BOOLEAN DEFAULT FALSE,

    -- Métadonnées
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_sync_inpi TIMESTAMP NULL,
    last_sync_sirene TIMESTAMP NULL,
    data_quality_score TINYINT DEFAULT 50,

    INDEX idx_siren (siren),
    INDEX idx_denomination (denomination),
    INDEX idx_ville (ville),
    INDEX idx_code_postal (code_postal),
    INDEX idx_secteur (secteur_activite),
    INDEX idx_updated (updated_at),
    FULLTEXT idx_search (denomination, denomination_usuelle, nom_commercial)
) ENGINE=InnoDB;

-- ============================================
-- Dirigeants et représentants légaux
-- ============================================
CREATE TABLE dirigeants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    siren VARCHAR(9) NOT NULL,
    type_personne ENUM('PM', 'PP') NOT NULL, -- Personne Morale/Physique

    -- Personne physique
    nom VARCHAR(100),
    prenom VARCHAR(100),
    nom_usage VARCHAR(100),
    date_naissance DATE,
    lieu_naissance VARCHAR(100),
    nationalite VARCHAR(50),

    -- Personne morale
    denomination_pm VARCHAR(255),
    siren_pm VARCHAR(9),
    forme_juridique_pm VARCHAR(100),

    -- Fonction
    fonction VARCHAR(100),
    date_debut DATE,
    date_fin DATE,
    actif BOOLEAN DEFAULT TRUE,

    -- Adresse
    adresse_ligne1 VARCHAR(255),
    code_postal VARCHAR(10),
    ville VARCHAR(100),
    pays VARCHAR(50),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company (company_id),
    INDEX idx_siren (siren),
    INDEX idx_nom (nom, prenom),
    INDEX idx_fonction (fonction)
) ENGINE=InnoDB;

-- ============================================
-- Établissements (SIRET)
-- ============================================
CREATE TABLE etablissements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    siren VARCHAR(9) NOT NULL,
    siret VARCHAR(14) UNIQUE NOT NULL,
    nic VARCHAR(5) NOT NULL,

    denomination_usuelle VARCHAR(255),
    enseigne VARCHAR(255),
    activite_principale VARCHAR(255),
    code_ape VARCHAR(10),

    -- Adresse
    adresse_ligne1 VARCHAR(255),
    adresse_ligne2 VARCHAR(255),
    code_postal VARCHAR(10),
    ville VARCHAR(100),
    cedex VARCHAR(50),

    -- Caractéristiques
    etablissement_siege BOOLEAN DEFAULT FALSE,
    etat_administratif ENUM('A','F') DEFAULT 'A',
    date_creation DATE,
    date_debut_activite DATE,
    date_fermeture DATE,

    effectif_salarie INT,
    tranche_effectif VARCHAR(10),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_siret (siret),
    INDEX idx_siren (siren),
    INDEX idx_ville (ville),
    INDEX idx_siege (etablissement_siege)
) ENGINE=InnoDB;

-- ============================================
-- Documents (bilans, actes, etc.)
-- ============================================
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    siren VARCHAR(9) NOT NULL,

    type_document ENUM('BILAN', 'ACTE', 'STATUTS', 'PV_AG', 'MODIFICATION', 'AUTRE'),
    sous_type VARCHAR(100),

    titre VARCHAR(255),
    description TEXT,

    date_document DATE,
    date_depot DATE,
    exercice_debut DATE,
    exercice_fin DATE,

    -- Fichier
    nom_fichier VARCHAR(255),
    chemin_fichier VARCHAR(500),
    taille_fichier INT,
    hash_fichier VARCHAR(64),

    -- Statut
    disponible BOOLEAN DEFAULT TRUE,
    telecharge BOOLEAN DEFAULT FALSE,
    date_telechargement TIMESTAMP NULL,

    -- Source
    source ENUM('INPI', 'BODACC', 'AUTRE') DEFAULT 'INPI',
    reference_externe VARCHAR(100),
    url_source VARCHAR(500),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company (company_id),
    INDEX idx_siren (siren),
    INDEX idx_type (type_document),
    INDEX idx_date_document (date_document),
    INDEX idx_exercice (exercice_fin)
) ENGINE=InnoDB;

-- ============================================
-- Données financières extraites des bilans
-- ============================================
CREATE TABLE donnees_financieres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    document_id INT,
    siren VARCHAR(9) NOT NULL,

    exercice_debut DATE NOT NULL,
    exercice_fin DATE NOT NULL,
    duree_exercice INT, -- en mois

    -- Bilan
    total_actif DECIMAL(15,2),
    total_passif DECIMAL(15,2),
    capitaux_propres DECIMAL(15,2),
    dettes_financieres DECIMAL(15,2),
    tresorerie DECIMAL(15,2),

    -- Compte de résultat
    chiffre_affaires DECIMAL(15,2),
    resultat_exploitation DECIMAL(15,2),
    resultat_financier DECIMAL(15,2),
    resultat_net DECIMAL(15,2),

    -- Effectifs
    effectif_moyen INT,
    effectif_fin_exercice INT,

    -- Ratios calculés
    ratio_endettement DECIMAL(5,2),
    ratio_liquidite DECIMAL(5,2),
    marge_exploitation DECIMAL(5,2),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_exercice (exercice_fin),
    INDEX idx_ca (chiffre_affaires),
    UNIQUE KEY uk_company_exercice (company_id, exercice_fin)
) ENGINE=InnoDB;

-- ============================================
-- Jugements et procédures collectives
-- ============================================
CREATE TABLE jugements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    siren VARCHAR(9) NOT NULL,

    type_jugement ENUM('REDRESSEMENT', 'LIQUIDATION', 'SAUVEGARDE', 'PLAN_CONTINUATION', 'AUTRE'),
    nature_jugement VARCHAR(255),

    tribunal VARCHAR(255),
    numero_rg VARCHAR(50),
    date_jugement DATE,
    date_publication DATE,

    -- Détails procédure
    administrateur_nom VARCHAR(255),
    mandataire_nom VARCHAR(255),
    liquidateur_nom VARCHAR(255),

    date_cessation_paiements DATE,
    date_limite_declarations DATE,

    description TEXT,

    -- Source
    source ENUM('BODACC', 'TRIBUNAL', 'AUTRE') DEFAULT 'BODACC',
    reference_bodacc VARCHAR(100),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company (company_id),
    INDEX idx_siren (siren),
    INDEX idx_type (type_jugement),
    INDEX idx_date_jugement (date_jugement),
    INDEX idx_tribunal (tribunal)
) ENGINE=InnoDB;

-- ============================================
-- Liens entre entreprises (participations, etc.)
-- ============================================
CREATE TABLE liens_entreprises (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_parent_id INT NOT NULL,
    company_enfant_id INT NOT NULL,
    siren_parent VARCHAR(9) NOT NULL,
    siren_enfant VARCHAR(9) NOT NULL,

    type_relation ENUM('FILIALE', 'PARTICIPATION', 'HOLDING', 'SUCCURSALE'),
    pourcentage_detention DECIMAL(5,2),

    date_debut DATE,
    date_fin DATE,
    actif BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (company_parent_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (company_enfant_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_parent (company_parent_id),
    INDEX idx_enfant (company_enfant_id),
    UNIQUE KEY uk_relation (company_parent_id, company_enfant_id, type_relation)
) ENGINE=InnoDB;

-- ============================================
-- Scores et évaluations
-- ============================================
CREATE TABLE scores_entreprises (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    siren VARCHAR(9) NOT NULL,

    score_solvabilite INT DEFAULT 50, -- 0-100
    score_croissance INT DEFAULT 50,
    score_rentabilite INT DEFAULT 50,
    score_global INT DEFAULT 50,

    -- Facteurs de risque
    risque_procedure_collective ENUM('FAIBLE', 'MOYEN', 'ELEVE') DEFAULT 'FAIBLE',
    risque_financier ENUM('FAIBLE', 'MOYEN', 'ELEVE') DEFAULT 'FAIBLE',

    -- Période de calcul
    date_calcul DATE NOT NULL,
    version_algorithme VARCHAR(20) DEFAULT '1.0',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company (company_id),
    INDEX idx_score_global (score_global),
    INDEX idx_date_calcul (date_calcul),
    UNIQUE KEY uk_company_date (company_id, date_calcul)
) ENGINE=InnoDB;

-- ============================================
-- Logs d'import et synchronisation
-- ============================================
CREATE TABLE import_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source ENUM('INPI', 'SIRENE', 'BODACC', 'MANUAL'),
    type_import ENUM('FULL', 'INCREMENTAL', 'SPECIFIC'),

    date_debut TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_fin TIMESTAMP NULL,
    statut ENUM('RUNNING', 'SUCCESS', 'ERROR', 'PARTIAL') DEFAULT 'RUNNING',

    nb_traites INT DEFAULT 0,
    nb_crees INT DEFAULT 0,
    nb_modifies INT DEFAULT 0,
    nb_erreurs INT DEFAULT 0,

    parametres JSON,
    message_erreur TEXT,

    INDEX idx_source (source),
    INDEX idx_date (date_debut),
    INDEX idx_statut (statut)
) ENGINE=InnoDB;

-- ============================================
-- Cache des recherches fréquentes
-- ============================================
CREATE TABLE search_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    query_hash VARCHAR(64) NOT NULL,
    query_text VARCHAR(500),
    results JSON,
    nb_resultats INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,

    INDEX idx_hash (query_hash),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ============================================
-- Utilisateurs et accès (si authentification nécessaire)
-- ============================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    entreprise VARCHAR(255),

    role ENUM('USER', 'ADMIN', 'API') DEFAULT 'USER',
    statut ENUM('ACTIF', 'SUSPENDU', 'EXPIRE') DEFAULT 'ACTIF',

    -- Quotas
    quota_recherches_jour INT DEFAULT 100,
    quota_documents_jour INT DEFAULT 20,
    nb_recherches_aujourd_hui INT DEFAULT 0,
    nb_documents_aujourd_hui INT DEFAULT 0,

    date_expiration DATE NULL,
    derniere_connexion TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ============================================
-- Tokens API
-- ============================================
CREATE TABLE api_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    nom VARCHAR(100),

    permissions JSON, -- ["search", "documents", "export"]

    nb_requetes_jour INT DEFAULT 0,
    limite_requetes_jour INT DEFAULT 1000,

    derniere_utilisation TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    actif BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token_hash),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ============================================
-- Vues utiles
-- ============================================

-- Vue des entreprises avec leurs dirigeants
CREATE VIEW v_companies_dirigeants AS
SELECT 
    c.id,
    c.siren,
    c.denomination,
    c.forme_juridique,
    c.ville,
    GROUP_CONCAT(
        CONCAT(d.prenom, ' ', d.nom, ' (', d.fonction, ')')
        SEPARATOR '; '
    ) as dirigeants
FROM companies c
LEFT JOIN dirigeants d ON c.id = d.company_id AND d.actif = TRUE
GROUP BY c.id;

-- Vue des entreprises avec dernières données financières
CREATE VIEW v_companies_financier AS
SELECT 
    c.id,
    c.siren,
    c.denomination,
    df.exercice_fin,
    df.chiffre_affaires,
    df.resultat_net,
    df.effectif_fin_exercice,
    s.score_global
FROM companies c
LEFT JOIN donnees_financieres df ON c.id = df.company_id 
    AND df.exercice_fin = (
        SELECT MAX(df2.exercice_fin) 
        FROM donnees_financieres df2 
        WHERE df2.company_id = c.id
    )
LEFT JOIN scores_entreprises s ON c.id = s.company_id
    AND s.date_calcul = (
        SELECT MAX(s2.date_calcul)
        FROM scores_entreprises s2
        WHERE s2.company_id = c.id
    );

SET foreign_key_checks = 1;

-- ============================================
-- Données de test
-- ============================================
INSERT INTO companies (siren, denomination, forme_juridique, ville, created_at) VALUES
('552120222', 'GOOGLE FRANCE', 'SAS', 'PARIS', NOW()),
('433455042', 'MICROSOFT FRANCE', 'SAS', 'ISSY LES MOULINEAUX', NOW()),
('552008443', 'FACEBOOK FRANCE', 'SAS', 'PARIS', NOW());

COMMIT;
