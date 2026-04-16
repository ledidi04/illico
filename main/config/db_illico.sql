-- Création de la base de données
DROP DATABASE IF EXISTS db_illico;
CREATE DATABASE db_illico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_illico;

-- Table des succursales
CREATE TABLE succursales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des succursales interconnectées
INSERT INTO succursales (code, nom, adresse, telephone, email) VALUES
('001', 'S&P illico – Succursale Principale', '123 Avenue Centrale, Centre-ville', '+509 2222-1111', 'principal@illico.ht'),
('002', 'S&P illico – Succursale Nord', '456 Boulevard du Nord, Quartier Industriel', '+509 2222-2222', 'nord@illico.ht');

-- Table des utilisateurs système
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    succursale_id INT NOT NULL,
    nom_complet VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','secretaire','caissier') NOT NULL,
    telephone VARCHAR(20),
    email VARCHAR(100),
    photo VARCHAR(255) DEFAULT NULL,
    derniere_connexion DATETIME DEFAULT NULL,
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (succursale_id) REFERENCES succursales(id) ON DELETE RESTRICT,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des utilisateurs par défaut (MOTS DE PASSE EN CLAIR POUR DÉVELOPPEMENT)
INSERT INTO utilisateurs (succursale_id, nom_complet, username, password, role, email, telephone) VALUES
(1, 'Administrateur Système', 'admin', 'admin123', 'admin', 'admin@illico.ht', '+509 3333-3333'),
(1, 'Marie Secrétaire', 'marie', 'marie123', 'secretaire', 'marie@illico.ht', '+509 3333-4444'),
(1, 'Jean Caissier', 'jean', 'jean123', 'caissier', 'jean@illico.ht', '+509 3333-5555'),
(2, 'Pierre Admin Nord', 'pierre', 'pierre123', 'admin', 'pierre@illico.ht', '+509 3333-6666');

-- Table des clients
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_client VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    date_naissance DATE,
    lieu_naissance VARCHAR(100),
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    photo VARCHAR(255) DEFAULT NULL,
    type_piece ENUM('NIF','CINU','PASSEPORT','AUTRE') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_client (id_client),
    INDEX idx_nom_prenom (nom, prenom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de quelques clients de test
INSERT INTO clients (id_client, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, email, type_piece) VALUES
('102-304-509-1', 'Pierre', 'Louis', '1985-06-15', 'Cap-Haïtien', '123 Rue du Centre', '+509 4444-1111', 'pierre.louis@email.com', 'NIF'),
('103-405-608-2', 'Marie', 'Jean', '1990-03-22', 'Port-au-Prince', '456 Avenue Nord', '+509 4444-2222', 'marie.jean@email.com', 'CINU'),
('104-506-707-3', 'Jean', 'Baptiste', '1978-11-08', 'Gonaïves', '789 Boulevard Sud', '+509 4444-3333', 'jean.baptiste@email.com', 'NIF');

-- Table des types de comptes
CREATE TABLE types_comptes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    nom VARCHAR(50) NOT NULL,
    description TEXT,
    taux_interet DECIMAL(5,2) DEFAULT 0.00,
    solde_minimum DECIMAL(15,2) DEFAULT 0.00,
    actif BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO types_comptes (code, nom, description, taux_interet, solde_minimum) VALUES
('EPARGNE', 'Compte Épargne', 'Compte d''épargne avec intérêts', 2.5, 1000.00),
('COURANT', 'Compte Courant', 'Compte courant pour opérations quotidiennes', 0.0, 500.00),
('EPARGNE_PLUS', 'Épargne Plus', 'Compte épargne avec taux préférentiel', 3.5, 5000.00);

-- Table des comptes bancaires
CREATE TABLE comptes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_compte CHAR(5) NOT NULL UNIQUE,
    succursale_id INT NOT NULL,
    type_compte_id INT NOT NULL,
    date_creation DATE NOT NULL,
    solde DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    titulaire_principal_id INT NOT NULL,
    statut ENUM('actif','bloque','cloture') DEFAULT 'actif',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (succursale_id) REFERENCES succursales(id),
    FOREIGN KEY (type_compte_id) REFERENCES types_comptes(id),
    FOREIGN KEY (titulaire_principal_id) REFERENCES clients(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id),
    INDEX idx_id_compte (id_compte),
    INDEX idx_solde (solde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de comptes de test
INSERT INTO comptes (succursale_id, type_compte_id, date_creation, solde, titulaire_principal_id, created_by, statut) VALUES
(1, 1, '2024-01-15', 15000.00, 1, 1, 'actif'),
(1, 2, '2024-02-20', 5000.00, 2, 1, 'actif'),
(2, 1, '2024-03-10', 25000.00, 3, 4, 'actif');

-- Table de liaison compte <-> co-titulaires
CREATE TABLE compte_cotitulaires (
    compte_id INT NOT NULL,
    client_id INT NOT NULL,
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (compte_id, client_id),
    FOREIGN KEY (compte_id) REFERENCES comptes(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des transactions
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compte_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    succursale_id INT NOT NULL,
    type ENUM('depot','retrait','virement','interet') NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    solde_avant DECIMAL(15,2) NOT NULL,
    solde_apres DECIMAL(15,2) NOT NULL,
    description TEXT,
    date_transaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compte_id) REFERENCES comptes(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (succursale_id) REFERENCES succursales(id),
    INDEX idx_compte_date (compte_id, date_transaction),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de transactions de test
INSERT INTO transactions (compte_id, utilisateur_id, succursale_id, type, montant, solde_avant, solde_apres, description) VALUES
(1, 3, 1, 'depot', 10000.00, 5000.00, 15000.00, 'Dépôt initial'),
(1, 3, 1, 'depot', 5000.00, 15000.00, 20000.00, 'Dépôt épargne'),
(2, 3, 1, 'depot', 5000.00, 0.00, 5000.00, 'Ouverture compte courant'),
(3, 3, 2, 'depot', 25000.00, 0.00, 25000.00, 'Dépôt épargne plus');

-- Table des sessions
CREATE TABLE sessions_utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs d'activités
CREATE TABLE logs_activites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supprimer les triggers existants s'ils existent
DROP TRIGGER IF EXISTS before_insert_compte;
DROP TRIGGER IF EXISTS after_compte_update;

-- Trigger pour générer automatiquement l'id_compte
DELIMITER //
CREATE TRIGGER before_insert_compte
BEFORE INSERT ON comptes
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    SELECT COALESCE(MAX(CAST(id_compte AS UNSIGNED)), 0) + 1 INTO next_id FROM comptes;
    SET NEW.id_compte = LPAD(next_id, 5, '0');
END//

DELIMITER ;

-- Vues pour les statistiques
CREATE OR REPLACE VIEW vue_statistiques_succursale AS
SELECT 
    s.id,
    s.code,
    s.nom,
    COUNT(DISTINCT c.id) as nombre_comptes,
    COALESCE(SUM(c.solde), 0) as total_depots,
    COUNT(DISTINCT u.id) as nombre_employes
FROM succursales s
LEFT JOIN comptes c ON s.id = c.succursale_id
LEFT JOIN utilisateurs u ON s.id = u.succursale_id
GROUP BY s.id;

-- Vue pour les statistiques globales
CREATE OR REPLACE VIEW vue_statistiques_globales AS
SELECT 
    (SELECT COUNT(*) FROM utilisateurs WHERE actif = 1) as total_utilisateurs,
    (SELECT COUNT(*) FROM clients) as total_clients,
    (SELECT COUNT(*) FROM comptes WHERE statut = 'actif') as total_comptes_actifs,
    (SELECT COALESCE(SUM(solde), 0) FROM comptes WHERE statut = 'actif') as total_depots,
    (SELECT COUNT(*) FROM transactions WHERE DATE(date_transaction) = CURDATE()) as transactions_jour;