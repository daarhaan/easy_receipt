<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_auth();
$uid  = $user['id'];
$id   = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM flats WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $uid]);
$flat = $stmt->fetch();
if (!$flat) {
    flash('error', 'Appartement introuvable.');
    redirect('/pages/flats.php');
}

// Suppression locataire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_tenant') {
    csrf_check();
    $tid = (int)($_POST['tenant_id'] ?? 0);
    db()->prepare('DELETE FROM tenants WHERE id = ? AND flat_id = ?')->execute([$tid, $id]);
    flash('success', 'Locataire supprimé.');
    redirect('/pages/flat_detail.php?id=' . $id);
}

// Locataires
$t_stmt = db()->prepare('SELECT * FROM tenants WHERE flat_id = ? ORDER BY active DESC, last_name, first_name');
$t_stmt->execute([$id]);
$tenants = $t_stmt->fetchAll();

// Quittances
$r_stmt = db()->prepare('
    SELECT r.*, CONCAT(t.first_name," ",t.last_name) AS tenant_name
    FROM receipts r
    JOIN tenants t ON t.id = r.tenant_id
    WHERE r.flat_id = ?
    ORDER BY r.period_year DESC, r.period_month DESC
');
$r_stmt->execute([$id]);
$receipts = $r_stmt->fetchAll();

$flash       = get_flash();
$page_title  = e($flat['name']);
$current_nav = 'flats';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif ?>

<div class="breadcrumb">
  <a href="/pages/flats.php">Appartements</a>
  <span class="sep">&rsaquo;</span>
  <?= e($flat['name']) ?>
</div>

<div class="page-header">
  <h1><?= e($flat['name']) ?></h1>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <a href="/pages/flat_form.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">Modifier</a>
    <a href="/pages/receipt_form.php?flat_id=<?= $id ?>" class="btn btn-primary">+ Quittance</a>
  </div>
</div>

<p style="color:var(--ink-light);margin-bottom:2rem"><?= nl2br(e($flat['address'])) ?></p>
<?php if ($flat['description']): ?>
<p style="margin-bottom:2rem"><?= nl2br(e($flat['description'])) ?></p>
<?php endif ?>

<!-- Locataires -->
<div class="page-header" style="margin-bottom:1rem">
  <h2>Locataires</h2>
  <a href="/pages/tenant_form.php?flat_id=<?= $id ?>" class="btn btn-ghost btn-sm">+ Ajouter</a>
</div>

<?php if (empty($tenants)): ?>
<div class="card" style="margin-bottom:2rem">
  <div class="empty-state" style="padding:2rem">
    <div class="icon">&#128100;</div>
    <p>Aucun locataire enregistré pour cet appartement.</p>
    <a href="/pages/tenant_form.php?flat_id=<?= $id ?>" class="btn btn-primary">Ajouter un locataire</a>
  </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:2rem">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Nom</th><th>Email</th><th>T&eacute;l&eacute;phone</th>
        <th>D&eacute;but bail</th><th>Fin bail</th><th>Statut</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($tenants as $t): ?>
      <tr>
        <td><strong><?= e($t['first_name'] . ' ' . $t['last_name']) ?></strong></td>
        <td><?= e($t['email'] ?? '') ?></td>
        <td><?= e($t['phone'] ?? '') ?></td>
        <td><?= french_date($t['lease_start']) ?></td>
        <td><?= $t['lease_end'] ? french_date($t['lease_end']) : '&mdash;' ?></td>
        <td>
          <?php if ($t['active']): ?>
            <span style="color:var(--sage);font-weight:500">Actif</span>
          <?php else: ?>
            <span style="color:var(--ink-light)">Inactif</span>
          <?php endif ?>
        </td>
        <td style="white-space:nowrap">
          <a href="/pages/tenant_form.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
          <a href="/pages/receipt_form.php?flat_id=<?= $id ?>&tenant_id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">+ Quittance</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ce locataire et ses quittances ?')">
            <input type="hidden" name="action" value="delete_tenant">
            <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
          </form>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<!-- Quittances -->
<div class="page-header" style="margin-bottom:1rem">
  <h2>Quittances</h2>
</div>

<?php if (empty($receipts)): ?>
<div class="card">
  <div class="empty-state" style="padding:2rem">
    <div class="icon">&#128196;</div>
    <p>Aucune quittance &eacute;mise pour cet appartement.</p>
    <?php if (!empty($tenants)): ?>
    <a href="/pages/receipt_form.php?flat_id=<?= $id ?>" class="btn btn-primary">Cr&eacute;er une quittance</a>
    <?php endif ?>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Locataire</th><th>P&eacute;riode</th>
        <th>Loyer</th><th>Charges</th><th>Total</th>
        <th>Paiement</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($receipts as $r): ?>
      <tr>
        <td><?= e($r['tenant_name']) ?></td>
        <td><?= e(french_month((int)$r['period_month'], (int)$r['period_year'])) ?></td>
        <td><?= money((float)$r['rent_amount']) ?></td>
        <td><?= money((float)$r['charges_amount']) ?></td>
        <td><strong><?= money((float)$r['total_amount']) ?></strong></td>
        <td><?= french_date($r['payment_date']) ?></td>
        <td style="white-space:nowrap">
          <a href="/pages/receipt_download.php?id=<?= $r['id'] ?>&action=pdf" class="btn btn-ghost btn-sm" target="_blank">PDF</a>
          <form method="post" action="/pages/receipts.php" style="display:inline"
                onsubmit="return confirm('Supprimer cette quittance ?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
          </form>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<?php require_once __DIR__ . '/../includes/footer.php' ?>
