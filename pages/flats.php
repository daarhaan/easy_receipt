<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_auth();
$uid  = $user['id'];

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM flats WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    flash('success', 'Appartement supprimé.');
    redirect('/pages/flats.php');
}

$stmt = db()->prepare('
    SELECT f.*,
        (SELECT COUNT(*) FROM tenants WHERE flat_id = f.id AND active = 1) AS tenant_count,
        (SELECT COUNT(*) FROM receipts WHERE flat_id = f.id) AS receipt_count
    FROM flats f WHERE f.user_id = ?
    ORDER BY f.name
');
$stmt->execute([$uid]);
$flats = $stmt->fetchAll();

$flash       = get_flash();
$page_title  = 'Appartements';
$current_nav = 'flats';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif ?>

<div class="page-header">
  <h1>Appartements</h1>
  <a href="/pages/flat_form.php" class="btn btn-primary">+ Ajouter</a>
</div>

<?php if (empty($flats)): ?>
<div class="empty-state">
  <div class="icon">&#127968;</div>
  <p>Aucun appartement enregistré.</p>
  <a href="/pages/flat_form.php" class="btn btn-primary">Ajouter un appartement</a>
</div>
<?php else: ?>
<div class="card-grid">
  <?php foreach ($flats as $flat): ?>
  <div class="flat-card">
    <h3><?= e($flat['name']) ?></h3>
    <div class="address"><?= nl2br(e($flat['address'])) ?></div>
    <div class="meta">
      <span class="badge"><?= $flat['tenant_count'] ?> locataire(s) actif(s)</span>
      <span class="badge"><?= $flat['receipt_count'] ?> quittance(s)</span>
    </div>
    <div class="actions">
      <a href="/pages/flat_detail.php?id=<?= $flat['id'] ?>" class="btn btn-ghost btn-sm">D&eacute;tails</a>
      <a href="/pages/flat_form.php?id=<?= $flat['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
      <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cet appartement et toutes ses données ?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $flat['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
      </form>
    </div>
  </div>
  <?php endforeach ?>
</div>
<?php endif ?>

<?php require_once __DIR__ . '/../includes/footer.php' ?>
