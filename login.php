<?php
// login.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

session_init();

// Déjà connecté → accueil
if (auth_user()) {
    redirect('/index.php');
}

$error = '';
$step  = 1; // 1 = identifiants, 2 = code TOTP

// Si une session 2FA est en attente, passer directement à l'étape 2
if (!empty($_SESSION['_2fa_pending'])) {
    $step = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login' && $step === 1) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = login($username, $password);
        if ($result === true) {
            redirect('/index.php');
        } elseif ($result === '2fa') {
            $step = 2;
        } else {
            $error = 'Identifiant ou mot de passe incorrect.';
        }
    }

    if ($action === 'verify_2fa' && $step === 2) {
        $code = preg_replace('/\s+/', '', $_POST['totp_code'] ?? '');
        if (verify_2fa_login($code)) {
            redirect('/index.php');
        } else {
            $error = 'Code invalide ou expiré. Réessayez.';
        }
    }

    if ($action === 'cancel_2fa') {
        unset($_SESSION['_2fa_pending']);
        redirect('/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connexion — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<div class="login-wrap">
  <div class="login-box">
    <div class="logo">🏠 <span>Quittances</span> de Loyer</div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif ?>

    <?php if ($step === 1): ?>
    <!-- Étape 1 : identifiants -->
    <form method="post" autocomplete="on">
      <input type="hidden" name="action" value="login">
      <div class="form-group">
        <label for="username">Identifiant</label>
        <input type="text" id="username" name="username"
               value="<?= e($_POST['username'] ?? '') ?>"
               required autofocus autocomplete="username">
      </div>
      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password"
               required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">Se connecter</button>
    </form>

    <?php else: ?>
    <!-- Étape 2 : code TOTP -->
    <p style="text-align:center;color:var(--ink-light);margin-bottom:1.5rem;font-size:.9rem">
      Saisissez le code à 6 chiffres de votre application d'authentification.
    </p>
    <form method="post" autocomplete="off">
      <input type="hidden" name="action" value="verify_2fa">
      <div class="form-group">
        <label for="totp_code">Code de vérification</label>
        <input type="text" id="totp_code" name="totp_code"
               inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7"
               placeholder="000 000"
               required autofocus autocomplete="one-time-code"
               style="font-size:1.5rem;letter-spacing:.2em;text-align:center">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-bottom:.75rem">Vérifier</button>
    </form>
    <form method="post">
      <input type="hidden" name="action" value="cancel_2fa">
      <button type="submit" class="btn btn-secondary" style="width:100%;font-size:.85rem">
        ← Retour à la connexion
      </button>
    </form>
    <?php endif ?>

  </div>
</div>

</body>
</html>
