<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

session_init();
$user = require_auth();
$uid  = $user['id'];
$pdo  = db();

// Stats
$s = $pdo->prepare('SELECT COUNT(*) FROM flats WHERE user_id = ?');
$s->execute([$uid]);
$nb_flats = (int)$s->fetchColumn();

$s = $pdo->prepare('SELECT COUNT(*) FROM tenants t JOIN flats f ON f.id = t.flat_id WHERE f.user_id = ? AND t.active = 1');
$s->execute([$uid]);
$nb_tenants = (int)$s->fetchColumn();

$cur_month = (int)date('n');
$cur_year  = (int)date('Y');

$s = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(total_amount),0) FROM receipts WHERE user_id = ? AND period_month = ? AND period_year = ?');
$s->execute([$uid, $cur_month, $cur_year]);
[$nb_receipts_month, $total_month] = $s->fetch(PDO::FETCH_NUM);

// Dernières quittances
$s = $pdo->prepare('
    SELECT r.*, f.name AS flat_name, CONCAT(t.first_name," ",t.last_name) AS tenant_name
    FROM receipts r
    JOIN flats f ON f.id = r.flat_id
    JOIN tenants t ON t.id = r.tenant_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 6
');
$s->execute([$uid]);
$recent_receipts = $s->fetchAll();

// Appartements
$s = $pdo->prepare('
    SELECT f.*,
        (SELECT COUNT(*) FROM tenants WHERE flat_id = f.id AND active = 1) AS tenant_count,
        (SELECT COUNT(*) FROM receipts WHERE flat_id = f.id) AS receipt_count
    FROM flats f WHERE f.user_id = ?
    ORDER BY f.name
');
$s->execute([$uid]);
$flats = $s->fetchAll();

$flash       = get_flash();
$page_title  = 'Tableau de bord';
$current_nav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif ?>

<div class="page-header">
  <h1>Tableau de bord</h1>
  <a href="/pages/receipt_form.php" class="btn btn-primary">+ Nouvelle quittance</a>
</div>

<div class="stats">
  <div class="stat-card">
    <div class="label">Appartements</div>
    <div class="value"><?= $nb_flats ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Locataires actifs</div>
    <div class="value"><?= $nb_tenants ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Quittances ce mois</div>
    <div class="value"><?= (int)$nb_receipts_month ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Encaiss&eacute; ce mois</div>
    <div class="value green"><?= money((float)$total_month) ?></div>
  </div>
</div>

<?php if (empty($flats)): ?>
<div class="empty-state">
  <div class="icon">&#127968;</div>
  <p>Aucun appartement enregistré.<br>Commencez par en ajouter un.</p>
  <a href="/pages/flat_form.php" class="btn btn-primary">Ajouter un appartement</a>
</div>
<?php else: ?>

<div class="page-header" style="margin-bottom:1rem">
  <h2>Mes appartements</h2>
  <a href="/pages/flats.php" class="btn btn-secondary btn-sm">Voir tout</a>
</div>

<div class="card-grid" style="margin-bottom:3rem">
  <?php foreach ($flats as $flat): ?>
  <div class="flat-card">
    <h3><?= e($flat['name']) ?></h3>
    <div class="address"><?= e($flat['address']) ?></div>
    <div class="meta">
      <span class="badge"><?= $flat['tenant_count'] ?> locataire(s)</span>
      <span class="badge"><?= $flat['receipt_count'] ?> quittance(s)</span>
    </div>
    <div class="actions">
      <a href="/pages/flat_detail.php?id=<?= $flat['id'] ?>" class="btn btn-ghost btn-sm">D&eacute;tails</a>
      <a href="/pages/receipt_form.php?flat_id=<?= $flat['id'] ?>" class="btn btn-primary btn-sm">+ Quittance</a>
    </div>
  </div>
  <?php endforeach ?>
</div>

<?php if (!empty($recent_receipts)): ?>
<h2 style="margin-bottom:1rem">Derni&egrave;res quittances</h2>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Appartement</th>
          <th>Locataire</th>
          <th>P&eacute;riode</th>
          <th>Montant</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent_receipts as $r): ?>
        <tr>
          <td><?= e($r['flat_name']) ?></td>
          <td><?= e($r['tenant_name']) ?></td>
          <td><?= e(french_month((int)$r['period_month'], (int)$r['period_year'])) ?></td>
          <td><?= money((float)$r['total_amount']) ?></td>
          <td><a href="/pages/receipt_download.php?id=<?= $r['id'] ?>&action=pdf" class="btn btn-ghost btn-sm" target="_blank">PDF</a></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<?php endif ?>

<?php require_once __DIR__ . '/includes/footer.php' ?>
