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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($username, $password)) {
        redirect('/index.php');
    } else {
        $error = 'Identifiant ou mot de passe incorrect.';
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

    <form method="post" autocomplete="on">
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
      <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>
  </div>
</div>

</body>
</html>
