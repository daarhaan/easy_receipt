<?php
// Bootstrap pour les tests — charge les constantes et helpers sans démarrer l'appli complète

define('APP_NAME',        'Quittances de Loyer');
define('APP_VERSION',     '1.0.0');
define('DB_HOST',         'localhost');
define('DB_NAME',         'rent_receipts_test');
define('DB_USER',         'root');
define('DB_PASS',         '');
define('DB_CHARSET',      'utf8mb4');
define('ROOT_PATH',       dirname(__DIR__));
define('RECEIPTS_PATH',   ROOT_PATH . '/receipts_storage');
define('BASE_URL',        'http://localhost');
define('SESSION_LIFETIME', 3600);

date_default_timezone_set('Europe/Paris');
setlocale(LC_ALL, 'fr_FR.UTF-8', 'fr_FR', 'fr');

require_once ROOT_PATH . '/includes/helpers.php';
