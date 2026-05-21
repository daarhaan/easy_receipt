<?php
// includes/header.php
// Variables attendues : $page_title (string), $current_nav (string), $user (array)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'Accueil') ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<nav class="navbar">
  <div class="container inner">
    <a href="/index.php" class="brand">&#127968; Quittances <span>de Loyer</span></a>
    <nav>
      <a href="/index.php" class="<?= ($current_nav ?? '') === 'dashboard' ? 'active' : '' ?>">Tableau de bord</a>
      <a href="/pages/flats.php" class="<?= ($current_nav ?? '') === 'flats' ? 'active' : '' ?>">Appartements</a>
      <a href="/pages/receipts.php" class="<?= ($current_nav ?? '') === 'receipts' ? 'active' : '' ?>">Quittances</a>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
      <a href="/pages/users.php" class="<?= ($current_nav ?? '') === 'users' ? 'active' : '' ?>">Utilisateurs</a>
      <?php endif ?>
      <a href="/pages/profile.php" class="<?= ($current_nav ?? '') === 'profile' ? 'active' : '' ?>">Mon profil</a>
    </nav>
    <div class="user-chip">
      Bonjour,&nbsp;<strong><?= e($user['full_name']) ?></strong>
      &nbsp;&middot;&nbsp;
      <a href="/logout.php" style="color:rgba(255,255,255,.65)">D&eacute;connexion</a>
    </div>
  </div>
</nav>

<main>
<div class="container">
