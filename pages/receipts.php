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
    $del_id = (int)($_POST['id'] ?? 0);
    $r = db()->prepare('SELECT pdf_filename FROM receipts WHERE id = ? AND user_id = ?');
    $r->execute([$del_id, $uid]);
    $row = $r->fetch();
    if ($row) {
        if ($row['pdf_filename'] && file_exists(RECEIPTS_PATH . '/' . $row['pdf_filename'])) {
            unlink(RECEIPTS_PATH . '/' . $row['pdf_filename']);
        }
        db()->prepare('DELETE FROM receipts WHERE id = ? AND user_id = ?')->execute([$del_id, $uid]);
        flash('success', 'Quittance supprimée.');
    }
    redirect('/pages/receipts.php');
}

// Filtres
$filter_flat  = (int)($_GET['flat_id'] ?? 0);
$filter_year  = (int)($_GET['year']    ?? 0);
$filter_month = (int)($_GET['month']   ?? 0);

$where  = ['r.user_id = ?'];
$params = [$uid];
if ($filter_flat)  { $where[] = 'r.flat_id = ?';      $params[] = $filter_flat; }
if ($filter_year)  { $where[] = 'r.period_year = ?';  $params[] = $filter_year; }
if ($filter_month) { $where[] = 'r.period_month = ?'; $params[] = $filter_month; }

$sql = 'SELECT r.*, f.name AS flat_name, CONCAT(t.first_name," ",t.last_name) AS tenant_name
        FROM receipts r
        JOIN flats f ON f.id = r.flat_id
        JOIN tenants t ON t.id = r.tenant_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY r.period_year DESC, r.period_month DESC, f.name';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll();

// Appartements pour le filtre
$flats_q = db()->prepare('SELECT id, name FROM flats WHERE user_id = ? ORDER BY name');
$flats_q->execute([$uid]);
$flats = $flats_q->fetchAll();

// Années disponibles
$years_q = db()->prepare('SELECT DISTINCT period_year FROM receipts WHERE user_id = ? ORDER BY period_year DESC');
$years_q->execute([$uid]);
$years = $years_q->fetchAll(PDO::FETCH_COLUMN);

$months_fr = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
              7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];

$flash       = get_flash();
$page_title  = 'Quittances';
$current_nav = 'receipts';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif ?>

<div class="page-header">
  <h1>Quittances</h1>
  <a href="/pages/receipt_form.php" class="btn btn-primary">+ Nouvelle quittance</a>
</div>

<!-- Filtres -->
<form method="get" class="card" style="margin-bottom:1.5rem;padding:1rem">
  <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="flex:1;min-width:180px">
      <label>Appartement</label>
      <select name="flat_id">
        <option value="">Tous</option>
        <?php foreach ($flats as $f): ?>
        <option value="<?= $f['id'] ?>" <?= $filter_flat === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="form-group" style="flex:1;min-width:120px">
      <label>Ann&eacute;e</label>
      <select name="year">
        <option value="">Toutes</option>
        <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>" <?= $filter_year === (int)$y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="form-group" style="flex:1;min-width:140px">
      <label>Mois</label>
      <select name="month">
        <option value="">Tous</option>
        <?php foreach ($months_fr as $n => $label): ?>
        <option value="<?= $n ?>" <?= $filter_month === $n ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filtrer</button>
    <?php if ($filter_flat || $filter_year || $filter_month): ?>
    <a href="/pages/receipts.php" class="btn btn-ghost">R&eacute;initialiser</a>
    <?php endif ?>
  </div>
</form>

<?php if (empty($receipts)): ?>
<div class="empty-state">
  <div class="icon">&#128196;</div>
  <p>Aucune quittance trouv&eacute;e.</p>
</div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Appartement</th><th>Locataire</th><th>P&eacute;riode</th>
        <th>Loyer</th><th>Charges</th><th>Total</th>
        <th>Paiement</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($receipts as $r): ?>
      <tr>
        <td><a href="/pages/flat_detail.php?id=<?= $r['flat_id'] ?>"><?= e($r['flat_name']) ?></a></td>
        <td><?= e($r['tenant_name']) ?></td>
        <td><?= e(french_month((int)$r['period_month'], (int)$r['period_year'])) ?></td>
        <td><?= money((float)$r['rent_amount']) ?></td>
        <td><?= money((float)$r['charges_amount']) ?></td>
        <td><strong><?= money((float)$r['total_amount']) ?></strong></td>
        <td><?= french_date($r['payment_date']) ?></td>
        <td style="white-space:nowrap">
          <a href="/pages/receipt_download.php?id=<?= $r['id'] ?>&action=pdf" class="btn btn-ghost btn-sm" target="_blank">PDF</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette quittance ?')">
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
