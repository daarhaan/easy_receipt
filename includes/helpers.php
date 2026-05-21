<?php
// includes/helpers.php — Fonctions utilitaires

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function money(float $amount): string {
    return number_format($amount, 2, ',', ' ') . ' €';
}

function french_month(int $month, int $year): string {
    $months = [
        1 => 'janvier', 2 => 'février', 3 => 'mars',
        4 => 'avril',   5 => 'mai',     6 => 'juin',
        7 => 'juillet', 8 => 'août',    9 => 'septembre',
       10 => 'octobre',11 => 'novembre',12 => 'décembre',
    ];
    return ($months[$month] ?? '?') . ' ' . $year;
}

function french_date(string $date): string {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d/m/Y') : $date;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Requête invalide (CSRF).');
    }
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
