<?php
// includes/auth.php — Authentification et gestion de session

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

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

function login(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['full_name']    = $user['full_name'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['_last_active'] = time();
        return true;
    }
    return false;
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
