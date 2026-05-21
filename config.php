<?php
// ============================================================
// config.php — Configuration centrale
// Copiez ce fichier et adaptez les valeurs à votre hébergement
// ============================================================

define('APP_NAME',    'Quittances de Loyer');
define('APP_VERSION', '1.0.0');

// --- Base de données -----------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'rent_receipts');
define('DB_USER', 'votre_user_bdd');      // ← à modifier
define('DB_PASS', 'votre_mot_de_passe'); // ← à modifier
define('DB_CHARSET', 'utf8mb4');

// --- Chemins ------------------------------------------------------------------
define('ROOT_PATH',     __DIR__);
define('RECEIPTS_PATH', ROOT_PATH . '/receipts_storage');
define('BASE_URL',      'https://votre-domaine.com'); // ← à modifier

// --- Session ------------------------------------------------------------------
define('SESSION_LIFETIME', 3600 * 8); // 8 heures

// --- Locale française ---------------------------------------------------------
setlocale(LC_ALL, 'fr_FR.UTF-8', 'fr_FR', 'fr');
date_default_timezone_set('Europe/Paris');

// --- Erreurs (désactivez en production) ---------------------------------------
ini_set('display_errors', 0);
error_reporting(E_ALL);
