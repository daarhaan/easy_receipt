-- ============================================================
-- Quittances de Loyer - Schéma de base de données
-- Compatible MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS rent_receipts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rent_receipts;

-- Utilisateurs
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(80) NOT NULL UNIQUE,
    email       VARCHAR(180) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(150) NOT NULL,
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Propriétaires (informations du bailleur, liées à un user)
CREATE TABLE landlords (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    address     TEXT NOT NULL,
    phone       VARCHAR(30),
    email       VARCHAR(180),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Appartements
CREATE TABLE flats (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    name        VARCHAR(100) NOT NULL,          -- Ex: "Appart T2 Rue Victor Hugo"
    address     TEXT NOT NULL,
    description TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Locataires
CREATE TABLE tenants (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    flat_id     INT NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(180),
    phone       VARCHAR(30),
    lease_start DATE NOT NULL,
    lease_end   DATE,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (flat_id) REFERENCES flats(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Quittances
CREATE TABLE receipts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    flat_id         INT NOT NULL,
    tenant_id       INT NOT NULL,
    user_id         INT NOT NULL,
    period_month    TINYINT(2) NOT NULL,        -- 1-12
    period_year     SMALLINT(4) NOT NULL,
    rent_amount     DECIMAL(10,2) NOT NULL,
    charges_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(10,2) GENERATED ALWAYS AS (rent_amount + charges_amount) STORED,
    payment_date    DATE NOT NULL,
    payment_mode    VARCHAR(60) NOT NULL DEFAULT 'Virement bancaire',
    notes           TEXT,
    pdf_filename    VARCHAR(255),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (flat_id)   REFERENCES flats(id)   ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    UNIQUE KEY uq_receipt (flat_id, tenant_id, period_month, period_year)
) ENGINE=InnoDB;

-- ============================================================
-- Compte admin par défaut  (mot de passe : admin1234)
-- Changez-le immédiatement après installation !
-- ============================================================
INSERT INTO users (username, email, password, full_name, role)
VALUES (
    'admin',
    'admin@example.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Administrateur',
    'admin'
);
