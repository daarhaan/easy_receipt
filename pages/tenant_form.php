<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_auth();
$uid  = $user['id'];

$id      = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
$flat_id = isset($_GET['flat_id']) ? (int)$_GET['flat_id'] : 0;
$tenant  = null;
$flat    = null;
$errors  = [];

if ($id) {
    $stmt = db()->prepare('
        SELECT t.*, f.user_id AS owner_id FROM tenants t
        JOIN flats f ON f.id = t.flat_id
        WHERE t.id = ?
    ');
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();
    if (!$tenant || (int)$tenant['owner_id'] !== $uid) {
        flash('error', 'Locataire introuvable.');
        redirect('/pages/flats.php');
    }
    $flat_id = (int)$tenant['flat_id'];
}

if ($flat_id) {
    $stmt = db()->prepare('SELECT * FROM flats WHERE id = ? AND user_id = ?');
    $stmt->execute([$flat_id, $uid]);
    $flat = $stmt->fetch();
    if (!$flat) {
        flash('error', 'Appartement introuvable.');
        redirect('/pages/flats.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $flat_id     = (int)($_POST['flat_id'] ?? $flat_id);
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $lease_start = trim($_POST['lease_start'] ?? '');
    $lease_end   = trim($_POST['lease_end'] ?? '') ?: null;
    $active      = isset($_POST['active']) ? 1 : 0;

    if (!$first_name)  $errors[] = 'Le prénom est obligatoire.';
    if (!$last_name)   $errors[] = 'Le nom est obligatoire.';
    if (!$lease_start) $errors[] = 'La date de début du bail est obligatoire.';

    if (empty($errors)) {
        if ($id) {
            db()->prepare('UPDATE tenants SET first_name=?,last_name=?,email=?,phone=?,lease_start=?,lease_end=?,active=? WHERE id=?')
               ->execute([$first_name, $last_name, $email, $phone, $lease_start, $lease_end, $active, $id]);
            flash('success', 'Locataire modifié.');
        } else {
            db()->prepare('INSERT INTO tenants (flat_id,first_name,last_name,email,phone,lease_start,lease_end,active) VALUES (?,?,?,?,?,?,?,1)')
               ->execute([$flat_id, $first_name, $last_name, $email, $phone, $lease_start, $lease_end]);
            flash('success', 'Locataire ajouté.');
        }
        redirect('/pages/flat_detail.php?id=' . $flat_id);
    }
    $tenant = compact('first_name', 'last_name', 'email', 'phone', 'lease_start', 'lease_end', 'active', 'flat_id');
}

$page_title  = $id ? 'Modifier le locataire' : 'Nouveau locataire';
$current_nav = 'flats';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="breadcrumb">
  <a href="/pages/flats.php">Appartements</a>
  <?php if ($flat): ?>
  <span class="sep">&rsaquo;</span>
  <a href="/pages/flat_detail.php?id=<?= $flat['id'] ?>"><?= e($flat['name']) ?></a>
  <?php endif ?>
  <span class="sep">&rsaquo;</span>
  <?= $id ? 'Modifier le locataire' : 'Nouveau locataire' ?>
</div>

<div class="page-header">
  <h1><?= $id ? 'Modifier le locataire' : 'Nouveau locataire' ?></h1>
</div>

<?php if ($errors): ?>
<div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif ?>

<div class="card" style="max-width:700px">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="flat_id" value="<?= $flat_id ?>">
    <div class="form-grid">
      <div class="form-group">
        <label for="first_name">Pr&eacute;nom *</label>
        <input type="text" id="first_name" name="first_name" value="<?= e($tenant['first_name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="last_name">Nom *</label>
        <input type="text" id="last_name" name="last_name" value="<?= e($tenant['last_name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($tenant['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="phone">T&eacute;l&eacute;phone</label>
        <input type="text" id="phone" name="phone" value="<?= e($tenant['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="lease_start">D&eacute;but du bail *</label>
        <input type="date" id="lease_start" name="lease_start" value="<?= e($tenant['lease_start'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="lease_end">Fin du bail</label>
        <input type="date" id="lease_end" name="lease_end" value="<?= e($tenant['lease_end'] ?? '') ?>">
        <span class="form-hint">Laisser vide si bail en cours</span>
      </div>
      <?php if ($id): ?>
      <div class="form-group full">
        <label style="flex-direction:row;align-items:center;gap:.5rem;cursor:pointer">
          <input type="checkbox" name="active" value="1" <?= ($tenant['active'] ?? 1) ? 'checked' : '' ?>>
          Locataire actif
        </label>
      </div>
      <?php endif ?>
    </div>
    <div style="display:flex;gap:.75rem;margin-top:1.5rem">
      <button type="submit" class="btn btn-primary">Enregistrer</button>
      <a href="<?= $flat_id ? '/pages/flat_detail.php?id=' . $flat_id : '/pages/flats.php' ?>" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php' ?>
