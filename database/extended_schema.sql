-- ============================================
-- SCHÉMA BASE DE DONNÉES ÉTENDU POUR SAAS
-- Entreprise Data Platform - Version SaaS
-- ============================================

-- Tables utilisateurs et authentification étendues
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    entreprise VARCHAR(255),
    phone VARCHAR(20),
    avatar VARCHAR(255),

    -- Plan et abonnement
    plan_type ENUM('free', 'starter', 'business', 'enterprise') DEFAULT 'free',
    plan_status ENUM('trial', 'active', 'past_due', 'canceled', 'expired') DEFAULT 'trial',
    trial_ends_at TIMESTAMP NULL,
    plan_expires_at TIMESTAMP NULL,

    -- Stripe
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),

    -- Quotas
    quota_recherches_jour INT DEFAULT 10,
    quota_documents_jour INT DEFAULT 2,
    quota_surveillance INT DEFAULT 0,
    nb_recherches_aujourd_hui INT DEFAULT 0,
    nb_documents_aujourd_hui INT DEFAULT 0,

    -- Utilisation mensuelle
    nb_recherches_ce_mois INT DEFAULT 0,
    nb_documents_ce_mois INT DEFAULT 0,
    nb_api_calls_ce_mois INT DEFAULT 0,

    -- Parrainage
    referral_code VARCHAR(20) UNIQUE,
    referred_by_code VARCHAR(20),
    referral_bonus_credits DECIMAL(10,2) DEFAULT 0,

    -- Vérification email
    email_verified_at TIMESTAMP NULL,
    email_verification_token VARCHAR(64),

    -- Sécurité
    password_reset_token VARCHAR(64),
    password_reset_expires TIMESTAMP NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(32),

    -- Préférences
    timezone VARCHAR(50) DEFAULT 'Europe/Paris',
    language VARCHAR(5) DEFAULT 'fr',
    notifications_email BOOLEAN DEFAULT TRUE,
    notifications_browser BOOLEAN DEFAULT TRUE,

    -- Tracking
    last_login_ip VARCHAR(45),
    derniere_connexion TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_email (email),
    INDEX idx_plan (plan_type, plan_status),
    INDEX idx_referral (referral_code),
    INDEX idx_stripe (stripe_customer_id)
) ENGINE=InnoDB;

-- Usage quotidien des utilisateurs
CREATE TABLE user_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date_usage DATE NOT NULL,

    recherches INT DEFAULT 0,
    documents INT DEFAULT 0,
    api_calls INT DEFAULT 0,
    exports INT DEFAULT 0,
    surveillances_actives INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_date (user_id, date_usage),
    INDEX idx_date (date_usage)
) ENGINE=InnoDB;

-- Système de surveillance des entreprises
CREATE TABLE surveillances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    siren VARCHAR(9) NOT NULL,
    denomination VARCHAR(255),

    -- Configuration surveillance
    type_surveillance ENUM('complete', 'dirigeants', 'financier', 'juridique', 'custom') DEFAULT 'complete',
    criteres_surveillance JSON, -- Critères personnalisés
    frequence_verification ENUM('hourly', 'daily', 'weekly') DEFAULT 'daily',

    -- Alertes
    alertes_email BOOLEAN DEFAULT TRUE,
    alertes_webhook VARCHAR(500),
    webhook_secret VARCHAR(64),

    -- Statut
    active BOOLEAN DEFAULT TRUE,
    derniere_verification TIMESTAMP NULL,
    prochaine_verification TIMESTAMP NULL,
    nb_changements_detectes INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_siren (siren),
    INDEX idx_active (active),
    INDEX idx_verification (prochaine_verification)
) ENGINE=InnoDB;

-- Snapshots des données surveillées
CREATE TABLE surveillance_snapshots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    surveillance_id INT NOT NULL,
    siren VARCHAR(9) NOT NULL,
    snapshot_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (surveillance_id) REFERENCES surveillances(id) ON DELETE CASCADE,
    INDEX idx_surveillance (surveillance_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Changements détectés
CREATE TABLE surveillance_changes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    surveillance_id INT NOT NULL,
    type_changement VARCHAR(100) NOT NULL,
    field_changed VARCHAR(100),
    old_value JSON,
    new_value JSON,
    importance ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',

    -- Notification
    notified BOOLEAN DEFAULT FALSE,
    notification_sent_at TIMESTAMP NULL,

    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (surveillance_id) REFERENCES surveillances(id) ON DELETE CASCADE,
    INDEX idx_surveillance (surveillance_id),
    INDEX idx_type (type_changement),
    INDEX idx_importance (importance),
    INDEX idx_date (detected_at)
) ENGINE=InnoDB;

-- Logs de paiement et transactions
CREATE TABLE payment_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    transaction_type ENUM('subscription_activated', 'payment_succeeded', 'payment_failed', 'subscription_canceled', 'refund') NOT NULL,

    -- Données Stripe
    stripe_session_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    stripe_invoice_id VARCHAR(255),

    -- Montants
    amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'EUR',

    -- Metadata
    plan_type VARCHAR(20),
    billing_period_start DATE,
    billing_period_end DATE,

    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Sessions utilisateur
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(128) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,

    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- API Tokens
CREATE TABLE api_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_name VARCHAR(100),
    token_hash VARCHAR(64) NOT NULL,

    -- Permissions
    permissions JSON, -- ["search", "company", "surveillance", "webhook"]

    -- Limites
    rate_limit INT DEFAULT 1000, -- par heure
    nb_requetes_aujourd_hui INT DEFAULT 0,
    derniere_requete TIMESTAMP NULL,

    -- Restriction IP
    allowed_ips JSON, -- Array d'IPs autorisées

    active BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_token (token_hash),
    INDEX idx_user (user_id),
    INDEX idx_active (active)
) ENGINE=InnoDB;

-- Logs d'activité utilisateur
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50), -- 'company', 'surveillance', 'user', etc.
    entity_id VARCHAR(50),

    -- Détails
    description TEXT,
    metadata JSON,

    -- Context
    ip_address VARCHAR(45),
    user_agent TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Système de parrainage
CREATE TABLE referral_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,

    -- Bonus accordés
    referrer_bonus_amount DECIMAL(10,2) DEFAULT 0,
    referred_bonus_days INT DEFAULT 0,

    bonus_applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_referrer (referrer_id),
    INDEX idx_referred (referred_id)
) ENGINE=InnoDB;

-- Cache des recherches avancé
CREATE TABLE search_cache_advanced (
    id INT PRIMARY KEY AUTO_INCREMENT,
    query_hash VARCHAR(64) NOT NULL,
    query_text VARCHAR(1000),
    filters JSON,
    user_plan VARCHAR(20),

    results JSON,
    nb_resultats INT,
    execution_time_ms INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    hit_count INT DEFAULT 1,

    UNIQUE KEY uk_hash (query_hash),
    INDEX idx_expires (expires_at),
    INDEX idx_plan (user_plan)
) ENGINE=InnoDB;

-- Blog et contenu SEO
CREATE TABLE blog_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    meta_description VARCHAR(320),
    meta_keywords TEXT,

    content LONGTEXT NOT NULL,
    excerpt TEXT,
    featured_image VARCHAR(500),

    -- SEO
    canonical_url VARCHAR(500),
    schema_markup JSON,

    -- Statut
    status ENUM('draft', 'published', 'scheduled', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,

    -- Catégories
    category VARCHAR(100),
    tags JSON,

    -- Métriques
    view_count INT DEFAULT 0,
    share_count INT DEFAULT 0,

    -- Auto-génération
    auto_generated BOOLEAN DEFAULT FALSE,
    source_company_siren VARCHAR(9),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_published (published_at),
    INDEX idx_siren (source_company_siren),
    FULLTEXT idx_content (title, content, meta_description)
) ENGINE=InnoDB;

-- Pages landing SEO
CREATE TABLE seo_landing_pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_type ENUM('sector', 'city', 'forme_juridique', 'keyword') NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,

    title VARCHAR(255) NOT NULL,
    meta_description VARCHAR(320),
    h1 VARCHAR(255),

    content LONGTEXT NOT NULL,
    sidebar_content TEXT,

    -- Données dynamiques
    dynamic_data JSON, -- Secteur, ville, etc.

    -- SEO
    canonical_url VARCHAR(500),
    robots VARCHAR(50) DEFAULT 'index,follow',

    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_slug (slug),
    INDEX idx_type (page_type),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Notifications système
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,

    type VARCHAR(100) NOT NULL, -- 'surveillance_alert', 'quota_warning', 'plan_expiry', etc.
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,

    -- Données associées
    related_entity_type VARCHAR(50),
    related_entity_id VARCHAR(50),
    metadata JSON,

    -- Actions
    action_url VARCHAR(500),
    action_text VARCHAR(100),

    -- Statut
    read_at TIMESTAMP NULL,
    archived_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (type),
    INDEX idx_read (read_at),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Webhooks utilisateur
CREATE TABLE user_webhooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,

    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(64),

    -- Configuration
    events JSON, -- ['surveillance.change', 'company.update', etc.]
    active BOOLEAN DEFAULT TRUE,

    -- Filtres
    filters JSON, -- Conditions pour déclencher le webhook

    -- Statistiques
    last_triggered_at TIMESTAMP NULL,
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_active (active)
) ENGINE=InnoDB;

-- Logs des webhooks
CREATE TABLE webhook_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    webhook_id INT NOT NULL,

    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,

    -- Résultat
    http_status INT,
    response_body TEXT,
    response_time_ms INT,

    success BOOLEAN,
    error_message TEXT,

    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (webhook_id) REFERENCES user_webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook (webhook_id),
    INDEX idx_event (event_type),
    INDEX idx_date (triggered_at),
    INDEX idx_success (success)
) ENGINE=InnoDB;

-- Système de gamification
CREATE TABLE user_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,

    action VARCHAR(100) NOT NULL,
    points INT NOT NULL,
    description VARCHAR(255),

    -- Contexte
    related_entity_type VARCHAR(50),
    related_entity_id VARCHAR(50),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Achievements / Badges
CREATE TABLE user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,

    achievement_type VARCHAR(100) NOT NULL, -- 'first_search', 'power_user', 'referrer', etc.
    achievement_data JSON,

    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_achievement (user_id, achievement_type),
    INDEX idx_type (achievement_type)
) ENGINE=InnoDB;

-- Métriques business temps réel
CREATE TABLE business_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,4) NOT NULL,
    metric_date DATE NOT NULL,

    -- Dimensions
    segment VARCHAR(100), -- 'plan_type', 'country', 'source', etc.
    segment_value VARCHAR(100),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_metric_date_segment (metric_name, metric_date, segment, segment_value),
    INDEX idx_metric (metric_name),
    INDEX idx_date (metric_date)
) ENGINE=InnoDB;

-- ============================================
-- VUES UTILES POUR LE BUSINESS
-- ============================================

-- Vue MRR (Monthly Recurring Revenue)
CREATE VIEW v_mrr AS
SELECT 
    DATE_FORMAT(CURDATE(), '%Y-%m') as month,
    SUM(
        CASE 
            WHEN plan_type = 'starter' THEN 29
            WHEN plan_type = 'business' THEN 99
            WHEN plan_type = 'enterprise' THEN 299
            ELSE 0
        END
    ) as mrr,
    COUNT(*) as active_subscriptions
FROM users 
WHERE plan_status = 'active'
GROUP BY month;

-- Vue utilisateurs actifs
CREATE VIEW v_active_users AS
SELECT 
    DATE(derniere_connexion) as date_connexion,
    COUNT(*) as daily_active_users
FROM users 
WHERE derniere_connexion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(derniere_connexion);

-- Vue conversion funnel
CREATE VIEW v_conversion_funnel AS
SELECT 
    'Visiteurs' as stage, 
    COUNT(*) as count, 
    100.0 as conversion_rate
FROM users
UNION ALL
SELECT 
    'Inscrits' as stage,
    COUNT(*) as count,
    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users)) as conversion_rate
FROM users 
WHERE created_at IS NOT NULL
UNION ALL
SELECT 
    'Payants' as stage,
    COUNT(*) as count,
    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users WHERE created_at IS NOT NULL)) as conversion_rate
FROM users 
WHERE plan_type != 'free';

-- ============================================
-- INDEX DE PERFORMANCE SUPPLÉMENTAIRES
-- ============================================

-- Tables principales déjà indexées dans le schéma de base
-- Ajout d'index spécifiques au SaaS

-- Performance des recherches par utilisateur
CREATE INDEX idx_companies_search_user ON companies(denomination(20), ville(20), secteur_activite(50));

-- Performance surveillance
CREATE INDEX idx_surveillance_verification ON surveillances(active, prochaine_verification);

-- Performance notifications
CREATE INDEX idx_notifications_unread ON notifications(user_id, read_at, created_at);

-- Performance métriques business
CREATE INDEX idx_metrics_reporting ON business_metrics(metric_name, metric_date, segment);

-- ============================================
-- DONNÉES DE RÉFÉRENCE
-- ============================================

-- Plans de tarification
INSERT INTO business_metrics (metric_name, metric_value, metric_date, segment, segment_value) VALUES
('plan_price', 0, CURDATE(), 'plan_type', 'free'),
('plan_price', 29, CURDATE(), 'plan_type', 'starter'),
('plan_price', 99, CURDATE(), 'plan_type', 'business'),
('plan_price', 299, CURDATE(), 'plan_type', 'enterprise');

-- Quotas par plan
INSERT INTO business_metrics (metric_name, metric_value, metric_date, segment, segment_value) VALUES
('quota_recherches', 10, CURDATE(), 'plan_type', 'free'),
('quota_recherches', 100, CURDATE(), 'plan_type', 'starter'),
('quota_recherches', 1000, CURDATE(), 'plan_type', 'business'),
('quota_recherches', 10000, CURDATE(), 'plan_type', 'enterprise');

-- ============================================
-- TRIGGERS POUR AUTOMATISATION
-- ============================================

-- Reset quotas quotidiens
DELIMITER $$
CREATE EVENT reset_daily_quotas
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE() + INTERVAL 1 DAY)
DO
BEGIN
    UPDATE users SET 
        nb_recherches_aujourd_hui = 0,
        nb_documents_aujourd_hui = 0;
END$$

-- Reset quotas mensuels
CREATE EVENT reset_monthly_quotas
ON SCHEDULE EVERY 1 MONTH
STARTS TIMESTAMP(DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01'))
DO
BEGIN
    UPDATE users SET 
        nb_recherches_ce_mois = 0,
        nb_documents_ce_mois = 0,
        nb_api_calls_ce_mois = 0;
END$$

-- Nettoyage automatique des données expirées
CREATE EVENT cleanup_expired_data
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    -- Supprimer les sessions expirées
    DELETE FROM user_sessions WHERE expires_at < NOW();

    -- Supprimer les tokens de réinitialisation expirés
    UPDATE users SET 
        password_reset_token = NULL,
        password_reset_expires = NULL
    WHERE password_reset_expires < NOW();

    -- Supprimer les anciens logs de webhook (> 90 jours)
    DELETE FROM webhook_logs WHERE triggered_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

    -- Supprimer les anciens snapshots de surveillance (> 365 jours)
    DELETE FROM surveillance_snapshots WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY);
END$$

DELIMITER ;

COMMIT;
