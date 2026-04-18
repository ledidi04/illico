-- ============================================================
-- S&P illico — Base de données CORRIGÉE
-- ============================================================

DROP DATABASE IF EXISTS db_illico;
CREATE DATABASE db_illico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_illico;

-- ── Succursales ───────────────────────────────────────────────
CREATE TABLE succursales (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(10)  NOT NULL UNIQUE,
    nom        VARCHAR(100) NOT NULL,
    adresse    TEXT,
    telephone  VARCHAR(20),
    email      VARCHAR(100),
    actif      BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO succursales (code, nom, adresse, telephone, email) VALUES
('001', 'S&P illico – Succursale Principale', '123 Avenue Centrale, Centre-ville',        '+509 2222-1111', 'principal@illico.ht'),
('002', 'S&P illico – Succursale Nord',       '456 Boulevard du Nord, Quartier Industriel','+509 2222-2222', 'nord@illico.ht');

-- ── Utilisateurs système ──────────────────────────────────────
CREATE TABLE utilisateurs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    succursale_id     INT          NOT NULL,
    nom_complet       VARCHAR(100) NOT NULL,
    username          VARCHAR(50)  NOT NULL UNIQUE,
    password          VARCHAR(255) NOT NULL,
    role              ENUM('admin','secretaire','caissier') NOT NULL,
    telephone         VARCHAR(20),
    email             VARCHAR(100),
    photo             VARCHAR(255) DEFAULT NULL,
    derniere_connexion DATETIME    DEFAULT NULL,
    actif             BOOLEAN      DEFAULT TRUE,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (succursale_id) REFERENCES succursales(id) ON DELETE RESTRICT,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO utilisateurs (succursale_id, nom_complet, username, password, role, email, telephone) VALUES
(1, 'Administrateur Système', 'admin',  'admin123',  'admin',      'admin@illico.ht',  '+509 3333-3333'),
(1, 'Marie Secrétaire',       'marie',  'marie123',  'secretaire', 'marie@illico.ht',  '+509 3333-4444'),
(1, 'Jean Caissier',          'jean',   'jean123',   'caissier',   'jean@illico.ht',   '+509 3333-5555'),
(2, 'Pierre Admin Nord',      'pierre', 'pierre123', 'admin',      'pierre@illico.ht', '+509 3333-6666');

-- ── Clients ───────────────────────────────────────────────────
CREATE TABLE clients (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    id_client      VARCHAR(20) NOT NULL UNIQUE,
    type_piece     ENUM('NIF','CINU','PASSEPORT','AUTRE') DEFAULT NULL,
    nom            VARCHAR(50)  NOT NULL,
    prenom         VARCHAR(50)  NOT NULL,
    date_naissance DATE,
    lieu_naissance VARCHAR(100),
    adresse        TEXT,
    telephone      VARCHAR(20),
    email          VARCHAR(100),
    photo          VARCHAR(255) DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_client  (id_client),
    INDEX idx_nom_prenom (nom, prenom),
    INDEX idx_telephone  (telephone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO clients (id_client, type_piece, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, email) VALUES
('102-304-509-1', 'NIF',  'Pierre',  'Louis',   '1985-06-15', 'Cap-Haïtien',    '123 Rue du Centre', '+509 4444-1111', 'pierre.louis@email.com'),
('103-405-608-2', 'CINU', 'Marie',   'Jean',    '1990-03-22', 'Port-au-Prince', '456 Avenue Nord',   '+509 4444-2222', 'marie.jean@email.com'),
('104-506-707-3', 'NIF',  'Jean',    'Baptiste','1978-11-08', 'Gonaïves',       '789 Boulevard Sud', '+509 4444-3333', 'jean.baptiste@email.com');

-- ── Types de comptes ──────────────────────────────────────────
CREATE TABLE types_comptes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(20) NOT NULL UNIQUE,
    nom            VARCHAR(60) NOT NULL,
    description    TEXT,
    categorie      ENUM('courant','epargne') NOT NULL DEFAULT 'courant',
    sous_type      ENUM('simple','bloque')   DEFAULT NULL,
    devise_defaut  ENUM('HTG','USD','EUR')   DEFAULT 'HTG',
    taux_interet   DECIMAL(5,2) DEFAULT 0.00,
    solde_minimum  DECIMAL(15,2) DEFAULT 0.00,
    actif          BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO types_comptes (code, nom, description, categorie, sous_type, devise_defaut, taux_interet, solde_minimum) VALUES
('CC_HTG',      'Compte Courant Gourdes',      'Compte courant en gourdes',                'courant', NULL,     'HTG', 0.00,  500.00),
('CC_USD',      'Compte Courant Dollars',      'Compte courant en dollars US',             'courant', NULL,     'USD', 0.00,  50.00),
('EP_HTG',      'Épargne Simple Gourdes',      'Compte épargne simple en gourdes',         'epargne', 'simple', 'HTG', 2.50, 1000.00),
('EP_HTG_BLQ',  'Épargne Bloquée Gourdes',     'Compte épargne bloqué en gourdes',         'epargne', 'bloque', 'HTG', 4.00, 5000.00),
('EP_USD',      'Épargne Simple Dollars',      'Compte épargne simple en dollars US',      'epargne', 'simple', 'USD', 2.00,  100.00);

-- ── Compteur de séquence pour id_compte ──────────────────────
CREATE TABLE seq_compte (
    derniere_valeur INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT INTO seq_compte VALUES (0);

-- ── Comptes bancaires ─────────────────────────────────────────
CREATE TABLE comptes (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    id_compte             CHAR(5)  NOT NULL UNIQUE,
    succursale_id         INT      NOT NULL,
    type_compte_id        INT      NOT NULL,
    devise                ENUM('HTG','USD','EUR') DEFAULT 'HTG',
    date_creation         DATE     NOT NULL,
    solde                 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    titulaire_principal_id INT     NOT NULL,
    statut                ENUM('actif','bloque','cloture') DEFAULT 'actif',
    created_by            INT      NOT NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (succursale_id)          REFERENCES succursales(id),
    FOREIGN KEY (type_compte_id)         REFERENCES types_comptes(id),
    FOREIGN KEY (titulaire_principal_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by)             REFERENCES utilisateurs(id),
    INDEX idx_id_compte (id_compte),
    INDEX idx_titulaire (titulaire_principal_id),
    INDEX idx_statut    (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS before_insert_compte;
DELIMITER //
CREATE TRIGGER before_insert_compte
BEFORE INSERT ON comptes
FOR EACH ROW
BEGIN
    DECLARE next_val INT;
    UPDATE seq_compte SET derniere_valeur = derniere_valeur + 1;
    SELECT derniere_valeur INTO next_val FROM seq_compte LIMIT 1;
    SET NEW.id_compte = LPAD(next_val, 5, '0');
END//
DELIMITER ;

INSERT INTO comptes (succursale_id, type_compte_id, date_creation, solde, titulaire_principal_id, created_by, statut, devise) VALUES
(1, 1, '2024-01-15', 15000.00, 1, 1, 'actif', 'HTG'),
(1, 2, '2024-02-20',  5000.00, 2, 1, 'actif', 'HTG'),
(2, 3, '2024-03-10', 25000.00, 3, 4, 'actif', 'HTG');

-- ── Co-titulaires ─────────────────────────────────────────────
CREATE TABLE compte_cotitulaires (
    compte_id  INT NOT NULL,
    client_id  INT NOT NULL,
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (compte_id, client_id),
    FOREIGN KEY (compte_id) REFERENCES comptes(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Transactions ──────────────────────────────────────────────
CREATE TABLE transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    compte_id        INT  NOT NULL,
    utilisateur_id   INT  NOT NULL,
    succursale_id    INT  NOT NULL,
    type             ENUM('depot','retrait','virement','interet') NOT NULL,
    montant          DECIMAL(15,2) NOT NULL CHECK (montant > 0),
    solde_avant      DECIMAL(15,2) NOT NULL,
    solde_apres      DECIMAL(15,2) NOT NULL,
    description      TEXT,
    date_transaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compte_id)      REFERENCES comptes(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (succursale_id)  REFERENCES succursales(id),
    INDEX idx_compte_date (compte_id, date_transaction),
    INDEX idx_type        (type),
    INDEX idx_date        (date_transaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO transactions (compte_id, utilisateur_id, succursale_id, type, montant, solde_avant, solde_apres, description) VALUES
(1, 3, 1, 'depot',   10000.00,     0.00, 10000.00, 'Dépôt initial'),
(1, 3, 1, 'depot',    5000.00, 10000.00, 15000.00, 'Dépôt épargne'),
(2, 3, 1, 'depot',    5000.00,     0.00,  5000.00, 'Ouverture compte courant'),
(3, 4, 2, 'depot',   25000.00,     0.00, 25000.00, 'Dépôt initial succursale Nord');

-- ── Sessions utilisateurs ─────────────────────────────────────
CREATE TABLE sessions_utilisateurs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    token         VARCHAR(255) NOT NULL UNIQUE,
    ip_address    VARCHAR(45),
    user_agent    TEXT,
    remember_me   BOOLEAN DEFAULT FALSE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at    DATETIME  NOT NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_token      (token),
    INDEX idx_expires    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Logs d'activités ──────────────────────────────────────────
CREATE TABLE logs_activites (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NULL,
    action        VARCHAR(100) NOT NULL,
    details       TEXT,
    ip_address    VARCHAR(45),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_action  (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Réinitialisation de mot de passe ─────────────────────────
CREATE TABLE password_resets (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    token         VARCHAR(255) NOT NULL UNIQUE,
    expires_at    DATETIME NOT NULL,
    used          BOOLEAN DEFAULT FALSE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Vues CORRIGÉES ────────────────────────────────────────────

-- Vue statistiques par succursale (corrigée)
CREATE OR REPLACE VIEW vue_statistiques_succursale AS
SELECT
    s.id,
    s.code,
    s.nom,
    COUNT(DISTINCT c.id)                         AS nombre_comptes,
    COALESCE(SUM(c.solde), 0)                    AS solde_total,
    COUNT(DISTINCT u.id)                         AS nombre_employes,
    COUNT(DISTINCT CASE WHEN c.statut = 'actif' THEN c.id END) AS comptes_actifs,
    COUNT(DISTINCT CASE WHEN c.statut = 'bloque' THEN c.id END) AS comptes_bloques
FROM succursales s
LEFT JOIN comptes c ON s.id = c.succursale_id
LEFT JOIN utilisateurs u ON s.id = u.succursale_id AND u.actif = 1
GROUP BY s.id, s.code, s.nom;

-- Vue statistiques globales (corrigée avec toutes les stats)
CREATE OR REPLACE VIEW vue_statistiques_globales AS
SELECT
    (SELECT COUNT(*) FROM utilisateurs WHERE actif = 1) AS total_utilisateurs,
    (SELECT COUNT(*) FROM clients) AS total_clients,
    (SELECT COUNT(*) FROM comptes WHERE statut = 'actif') AS total_comptes_actifs,
    (SELECT COUNT(*) FROM comptes WHERE statut = 'bloque') AS total_comptes_bloques,
    (SELECT COALESCE(SUM(solde), 0) FROM comptes WHERE statut = 'actif') AS total_depots,
    (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'retrait') AS total_retraits,
    (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'depot') AS total_depots_all,
    (SELECT COUNT(*) FROM transactions WHERE type = 'retrait') AS nb_retraits,
    (SELECT COUNT(*) FROM transactions WHERE type = 'depot') AS nb_depots,
    (SELECT COUNT(*) FROM transactions WHERE DATE(date_transaction) = CURDATE()) AS transactions_jour,
    (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'depot' AND DATE(date_transaction) = CURDATE()) AS depots_jour,
    (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'retrait' AND DATE(date_transaction) = CURDATE()) AS retraits_jour;