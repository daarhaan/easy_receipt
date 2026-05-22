<?php
// includes/auth.php — Authentification et gestion de session

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\QRServerProvider;

function session_init(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    // Expiration manuelle de session
    if (isset($_SESSION['_last_active']) && (time() - $_SESSION['_last_active']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['_last_active'] = time();
}

function get_tfa(): TwoFactorAuth {
    static $tfa = null;
    if ($tfa === null) {
        $tfa = new TwoFactorAuth(new QRServerProvider(), APP_NAME);
    }
    return $tfa;
}

/**
 * Vérifie les identifiants. Retourne :
 *   true    — connexion complète (pas de 2FA)
 *   '2fa'   — mot de passe valide, code TOTP requis
 *   false   — identifiants invalides
 */
function login(string $username, string $password): bool|string {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    if (!empty($user['totp_enabled'])) {
        // Stocker l'état intermédiaire dans la session
        session_regenerate_id(true);
        $_SESSION['_2fa_pending'] = [
            'id'          => (int)$user['id'],
            'username'    => $user['username'],
            'full_name'   => $user['full_name'],
            'role'        => $user['role'],
            'totp_secret' => $user['totp_secret'],
        ];
        return '2fa';
    }

    _set_session_from_user($user);
    return true;
}

/**
 * Vérifie le code TOTP après l'étape mot de passe.
 * Retourne true si le code est valide et la session est ouverte.
 */
function verify_2fa_login(string $code): bool {
    if (empty($_SESSION['_2fa_pending'])) {
        return false;
    }
    $pending = $_SESSION['_2fa_pending'];
    if (!get_tfa()->verifyCode($pending['totp_secret'], $code)) {
        return false;
    }
    unset($_SESSION['_2fa_pending']);
    _set_session_from_array($pending);
    return true;
}

function _set_session_from_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']      = (int)$user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['full_name']    = $user['full_name'];
    $_SESSION['role']         = $user['role'];
    $_SESSION['_last_active'] = time();
}

function _set_session_from_array(array $data): void {
    session_regenerate_id(true);
    $_SESSION['user_id']      = $data['id'];
    $_SESSION['username']     = $data['username'];
    $_SESSION['full_name']    = $data['full_name'];
    $_SESSION['role']         = $data['role'];
    $_SESSION['_last_active'] = time();
}

function logout(): void {
    session_unset();
    session_destroy();
}

function auth_user(): ?array {
    if (!empty($_SESSION['user_id'])) {
        return [
            'id'        => (int)$_SESSION['user_id'],
            'username'  => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role'      => $_SESSION['role'],
        ];
    }
    return null;
}

function require_auth(): array {
    $user = auth_user();
    if (!$user) {
        redirect('/login.php');
    }
    return $user;
}

function require_admin(): array {
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        die('Accès refusé.');
    }
    return $user;
}
