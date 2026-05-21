<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_auth();
$uid  = $user['id'];

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flat = null;
$errors = [];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM flats WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
    $flat = $stmt->fetch();
    if (!$flat) {
        flash('error', 'Appartement introuvable.');
        redirect('/pages/flats.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name         = trim($_POST['name'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $desc         = trim($_POST['description'] ?? '');
    $mandate_type = $_POST['mandate_type'] === 'mandataire' ? 'mandataire' : 'proprietaire';

    if (!$name)    $errors[] = 'Le nom est obligatoire.';
    if (!$address) $errors[] = "L'adresse est obligatoire.";

    if (empty($errors)) {
        if ($id) {
            db()->prepare('UPDATE flats SET name=?, address=?, description=?, mandate_type=? WHERE id=? AND user_id=?')
               ->execute([$name, $address, $desc, $mandate_type, $id, $uid]);
            flash('success', 'Appartement modifié.');
        } else {
            db()->prepare('INSERT INTO flats (user_id, name, address, description, mandate_type) VALUES (?,?,?,?,?)')
               ->execute([$uid, $name, $address, $desc, $mandate_type]);
            flash('success', 'Appartement ajouté.');
        }
        redirect('/pages/flats.php');
    }
    $flat = ['name' => $name, 'address' => $address, 'description' => $desc, 'mandate_type' => $mandate_type];
}

$page_title  = $id ? 'Modifier un appartement' : 'Nouvel appartement';
$current_nav = 'flats';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="breadcrumb">
  <a href="/pages/flats.php">Appartements</a>
  <span class="sep">&rsaquo;</span>
  <?= $id ? 'Modifier' : 'Ajouter' ?>
</div>

<div class="page-header">
  <h1><?= $id ? "Modifier l'appartement" : 'Nouvel appartement' ?></h1>
</div>

<?php if ($errors): ?>
<div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif ?>

<div class="card" style="max-width:600px">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="form-grid">
      <div class="form-group full">
        <label for="name">Nom / R&eacute;f&eacute;rence *</label>
        <input type="text" id="name" name="name" value="<?= e($flat['name'] ?? '') ?>" required
               placeholder="Ex : Appart T2 Rue Victor Hugo">
      </div>
      <div class="form-group full">
        <label for="address">Adresse compl&egrave;te *</label>
        <textarea id="address" name="address" required
                  placeholder="12 rue Victor Hugo&#10;75011 Paris"><?= e($flat['address'] ?? '') ?></textarea>
      </div>
      <div class="form-group full">
        <label for="description">Description (optionnelle)</label>
        <textarea id="description" name="description"
                  placeholder="Notes internes..."><?= e($flat['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group full">
        <label>Qualit&eacute; du bailleur</label>
        <div style="display:flex;gap:1.5rem;margin-top:.25rem">
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400">
            <input type="radio" name="mandate_type" value="proprietaire"
              <?= ($flat['mandate_type'] ?? 'proprietaire') === 'proprietaire' ? 'checked' : '' ?>>
            Propri&eacute;taire
          </label>
          <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400">
            <input type="radio" name="mandate_type" value="mandataire"
              <?= ($flat['mandate_type'] ?? '') === 'mandataire' ? 'checked' : '' ?>>
            Mandataire
          </label>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:.75rem;margin-top:1.5rem">
      <button type="submit" class="btn btn-primary">Enregistrer</button>
      <a href="/pages/flats.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php' ?>
