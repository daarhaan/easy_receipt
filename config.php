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
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Chemins ------------------------------------------------------------------
define('ROOT_PATH',     __DIR__);
define('RECEIPTS_PATH', ROOT_PATH . '/receipts_storage');
define('BASE_URL',      'http://localhost/easy_receipt');

// --- Session ------------------------------------------------------------------
define('SESSION_LIFETIME', 3600 * 8); // 8 heures

// --- Locale française ---------------------------------------------------------
setlocale(LC_ALL, 'fr_FR.UTF-8', 'fr_FR', 'fr');
date_default_timezone_set('Europe/Paris');

// --- Erreurs (désactivez en production) ---------------------------------------
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- Autoload Composer --------------------------------------------------------
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
