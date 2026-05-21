<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_auth();
$uid  = $user['id'];

// Données utilisateur
$u_stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$u_stmt->execute([$uid]);
$u = $u_stmt->fetch();

// Profil bailleur
$l_stmt = db()->prepare('SELECT * FROM landlords WHERE user_id = ? LIMIT 1');
$l_stmt->execute([$uid]);
$landlord = $l_stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $section = $_POST['section'] ?? '';

    if ($section === 'account') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $new_pass  = $_POST['new_password'] ?? '';
        $cur_pass  = $_POST['current_password'] ?? '';

        if (!$full_name) $errors[] = 'Le nom complet est obligatoire.';
        if (!$email)     $errors[] = "L'email est obligatoire.";
        if ($new_pass) {
            if (!password_verify($cur_pass, $u['password'])) {
                $errors[] = 'Mot de passe actuel incorrect.';
            } elseif (strlen($new_pass) < 8) {
                $errors[] = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
            }
        }

        if (empty($errors)) {
            if ($new_pass) {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
                db()->prepare('UPDATE users SET full_name=?,email=?,password=? WHERE id=?')
                   ->execute([$full_name, $email, $hash, $uid]);
            } else {
                db()->prepare('UPDATE users SET full_name=?,email=? WHERE id=?')
                   ->execute([$full_name, $email, $uid]);
            }
            $_SESSION['full_name'] = $full_name;
            $user['full_name']     = $full_name;
            $u['full_name']        = $full_name;
            $u['email']            = $email;
            flash('success', 'Compte mis à jour.');
            redirect('/pages/profile.php');
        }
    }

    if ($section === 'landlord') {
        $name    = trim($_POST['landlord_name']    ?? '');
        $address = trim($_POST['landlord_address'] ?? '');
        $phone   = trim($_POST['landlord_phone']   ?? '');
        $lemail  = trim($_POST['landlord_email']   ?? '');

        if (!$name)    $errors[] = 'Le nom du bailleur est obligatoire.';
        if (!$address) $errors[] = "L'adresse du bailleur est obligatoire.";

        if (empty($errors)) {
            if ($landlord) {
                db()->prepare('UPDATE landlords SET name=?,address=?,phone=?,email=? WHERE user_id=?')
                   ->execute([$name, $address, $phone, $lemail, $uid]);
            } else {
                db()->prepare('INSERT INTO landlords (user_id,name,address,phone,email) VALUES (?,?,?,?,?)')
                   ->execute([$uid, $name, $address, $phone, $lemail]);
            }
            // Recharger
            $l_stmt->execute([$uid]);
            $landlord = $l_stmt->fetch();
            flash('success', 'Profil bailleur mis à jour.');
            redirect('/pages/profile.php');
        }
    }
}

$flash       = get_flash();
$page_title  = 'Mon profil';
$current_nav = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif ?>
<?php if ($errors): ?>
<div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif ?>

<div class="page-header">
  <h1>Mon profil</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:900px">

  <!-- Compte -->
  <div class="card">
    <h2 style="margin-bottom:1.5rem">Compte</h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="section" value="account">
      <div class="form-group" style="margin-bottom:1rem">
        <label>Identifiant</label>
        <input type="text" value="<?= e($u['username']) ?>" disabled style="background:var(--cream)">
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label for="full_name">Nom complet *</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($u['full_name']) ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label for="email">Email *</label>
        <input type="email" id="email" name="email" value="<?= e($u['email']) ?>" required>
      </div>
      <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border)">
      <h3 style="margin-bottom:1rem;font-size:1rem;font-family:'DM Sans',sans-serif">Changer de mot de passe</h3>
      <div class="form-group" style="margin-bottom:1rem">
        <label for="current_password">Mot de passe actuel</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password">
      </div>
      <div class="form-group" style="margin-bottom:1.5rem">
        <label for="new_password">Nouveau mot de passe</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password">
        <span class="form-hint">Laisser vide pour ne pas changer (min. 8 caractères)</span>
      </div>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
  </div>

  <!-- Bailleur -->
  <div class="card">
    <h2 style="margin-bottom:.4rem">Profil bailleur</h2>
    <p style="color:var(--ink-light);font-size:.875rem;margin-bottom:1.5rem">
      Ces informations apparaissent sur vos quittances PDF.
    </p>
    <?php if (empty($landlord)): ?>
    <div class="alert alert-info" style="margin-bottom:1.5rem">
      &#9888; Profil non configuré &mdash; remplissez ce formulaire avant de générer des PDF.
    </div>
    <?php endif ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="section" value="landlord">
      <div class="form-group" style="margin-bottom:1rem">
        <label for="landlord_name">Nom (bailleur) *</label>
        <input type="text" id="landlord_name" name="landlord_name"
               value="<?= e($landlord['name'] ?? '') ?>" required placeholder="Jean Dupont">
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label for="landlord_address">Adresse *</label>
        <textarea id="landlord_address" name="landlord_address" required
                  placeholder="5 rue de la Paix&#10;75001 Paris"><?= e($landlord['address'] ?? '') ?></textarea>
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label for="landlord_phone">T&eacute;l&eacute;phone</label>
        <input type="text" id="landlord_phone" name="landlord_phone" value="<?= e($landlord['phone'] ?? '') ?>">
      </div>
      <div class="form-group" style="margin-bottom:1.5rem">
        <label for="landlord_email">Email</label>
        <input type="email" id="landlord_email" name="landlord_email" value="<?= e($landlord['email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php' ?>
