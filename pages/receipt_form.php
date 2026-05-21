<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_auth();
$uid  = $user['id'];

$pre_flat   = (int)($_GET['flat_id']   ?? 0);
$pre_tenant = (int)($_GET['tenant_id'] ?? 0);

// Profil bailleur
$landlord_q = db()->prepare('SELECT * FROM landlords WHERE user_id = ? LIMIT 1');
$landlord_q->execute([$uid]);
$landlord = $landlord_q->fetch();

// Appartements
$flats_q = db()->prepare('SELECT id, name FROM flats WHERE user_id = ? ORDER BY name');
$flats_q->execute([$uid]);
$flats = $flats_q->fetchAll();

// Locataires actifs (tous, filtrés en JS)
$tenants_q = db()->prepare('
    SELECT t.id, t.flat_id, t.first_name, t.last_name
    FROM tenants t JOIN flats f ON f.id = t.flat_id
    WHERE f.user_id = ? AND t.active = 1
    ORDER BY t.last_name, t.first_name
');
$tenants_q->execute([$uid]);
$all_tenants = $tenants_q->fetchAll();

// Pré-remplissage depuis la dernière quittance de l'appartement
$last_rent = ['rent_amount' => '', 'charges_amount' => '0.00'];
if ($pre_flat) {
    $lr = db()->prepare('SELECT rent_amount, charges_amount FROM receipts WHERE flat_id = ? ORDER BY period_year DESC, period_month DESC LIMIT 1');
    $lr->execute([$pre_flat]);
    $row = $lr->fetch();
    if ($row) $last_rent = $row;
}

$errors = [];
$form = [
    'flat_id'        => $pre_flat,
    'tenant_id'      => $pre_tenant,
    'period_month'   => (int)date('n'),
    'period_year'    => (int)date('Y'),
    'rent_amount'    => $last_rent['rent_amount'],
    'charges_amount' => $last_rent['charges_amount'],
    'payment_date'   => date('Y-m-d'),
    'payment_mode'   => 'Virement bancaire',
    'notes'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $flat_id        = (int)($_POST['flat_id'] ?? 0);
    $tenant_id      = (int)($_POST['tenant_id'] ?? 0);
    $period_month   = (int)($_POST['period_month'] ?? 0);
    $period_year    = (int)($_POST['period_year'] ?? 0);
    $rent_amount    = (float)str_replace(',', '.', $_POST['rent_amount'] ?? '');
    $charges_amount = (float)str_replace(',', '.', $_POST['charges_amount'] ?? '0');
    $payment_date   = trim($_POST['payment_date'] ?? '');
    $payment_mode   = trim($_POST['payment_mode'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');

    if (!$flat_id)   $errors[] = 'Sélectionnez un appartement.';
    if (!$tenant_id) $errors[] = 'Sélectionnez un locataire.';
    if ($period_month < 1 || $period_month > 12) $errors[] = 'Mois invalide.';
    if ($period_year < 2000) $errors[] = 'Année invalide.';
    if ($rent_amount <= 0)   $errors[] = 'Le montant du loyer doit être positif.';
    if (!$payment_date)      $errors[] = 'La date de paiement est obligatoire.';
    if (!$payment_mode)      $errors[] = 'Le mode de paiement est obligatoire.';

    // Vérification propriété
    if ($flat_id) {
        $owns = db()->prepare('SELECT id FROM flats WHERE id = ? AND user_id = ?');
        $owns->execute([$flat_id, $uid]);
        if (!$owns->fetch()) $errors[] = 'Appartement invalide.';
    }

    if (empty($errors)) {
        try {
            $stmt = db()->prepare('
                INSERT INTO receipts (flat_id,tenant_id,user_id,period_month,period_year,rent_amount,charges_amount,payment_date,payment_mode,notes)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ');
            $stmt->execute([$flat_id, $tenant_id, $uid, $period_month, $period_year, $rent_amount, $charges_amount, $payment_date, $payment_mode, $notes]);
            $receipt_id = (int)db()->lastInsertId();
            flash('success', 'Quittance créée avec succès.');
            redirect('/pages/receipt_download.php?id=' . $receipt_id . '&action=pdf');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Une quittance existe déjà pour cet appartement, ce locataire et cette période.';
            } else {
                throw $e;
            }
        }
    }
    $form = compact('flat_id', 'tenant_id', 'period_month', 'period_year', 'rent_amount', 'charges_amount', 'payment_date', 'payment_mode', 'notes');
}

$payment_modes = ['Virement bancaire', 'Chèque', 'Espèces', 'Prélèvement automatique', 'Autre'];
$months_fr = [
    1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril', 5=>'Mai', 6=>'Juin',
    7=>'Juillet', 8=>'Août', 9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre',
];

$page_title  = 'Nouvelle quittance';
$current_nav = 'receipts';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$landlord): ?>
<div class="alert alert-info">
  &#9888; Vous n'avez pas encore configuré votre profil bailleur (nécessaire pour générer les PDF).
  <a href="/pages/profile.php"><strong>Configurer maintenant</strong></a>
</div>
<?php endif ?>

<div class="breadcrumb">
  <a href="/pages/receipts.php">Quittances</a>
  <span class="sep">&rsaquo;</span> Nouvelle quittance
</div>

<div class="page-header">
  <h1>Nouvelle quittance</h1>
</div>

<?php if ($errors): ?>
<div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif ?>

<?php if (empty($flats)): ?>
<div class="alert alert-info">
  Vous devez d'abord <a href="/pages/flat_form.php">ajouter un appartement</a> avant de créer une quittance.
</div>
<?php else: ?>

<div class="card" style="max-width:700px">
  <form method="post" id="receipt-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="form-grid">

      <div class="form-group">
        <label for="flat_id">Appartement *</label>
        <select id="flat_id" name="flat_id" required onchange="filterTenants()">
          <option value="">— Choisir —</option>
          <?php foreach ($flats as $f): ?>
          <option value="<?= $f['id'] ?>" <?= (int)$form['flat_id'] === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="form-group">
        <label for="tenant_id">Locataire *</label>
        <select id="tenant_id" name="tenant_id" required>
          <option value="">— Choisir un appart. d'abord —</option>
        </select>
      </div>

      <div class="form-group">
        <label for="period_month">Mois *</label>
        <select id="period_month" name="period_month" required>
          <?php foreach ($months_fr as $n => $label): ?>
          <option value="<?= $n ?>" <?= (int)$form['period_month'] === $n ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="form-group">
        <label for="period_year">Ann&eacute;e *</label>
        <select id="period_year" name="period_year" required>
          <?php for ($y = (int)date('Y') + 1; $y >= 2015; $y--): ?>
          <option value="<?= $y ?>" <?= (int)$form['period_year'] === $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor ?>
        </select>
      </div>

      <div class="form-group">
        <label for="rent_amount">Loyer nu (&euro;) *</label>
        <input type="number" id="rent_amount" name="rent_amount" step="0.01" min="0.01"
               value="<?= e($form['rent_amount']) ?>" required placeholder="800.00">
      </div>

      <div class="form-group">
        <label for="charges_amount">Charges (&euro;)</label>
        <input type="number" id="charges_amount" name="charges_amount" step="0.01" min="0"
               value="<?= e($form['charges_amount']) ?>" placeholder="50.00">
      </div>

      <div class="form-group">
        <label for="payment_date">Date de paiement *</label>
        <input type="date" id="payment_date" name="payment_date" value="<?= e($form['payment_date']) ?>" required>
      </div>

      <div class="form-group">
        <label for="payment_mode">Mode de paiement *</label>
        <select id="payment_mode" name="payment_mode" required>
          <?php foreach ($payment_modes as $pm): ?>
          <option value="<?= e($pm) ?>" <?= $form['payment_mode'] === $pm ? 'selected' : '' ?>><?= e($pm) ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="form-group full">
        <label for="notes">Notes (optionnelles)</label>
        <textarea id="notes" name="notes" placeholder="Observations, d&eacute;tails..."><?= e($form['notes']) ?></textarea>
      </div>

    </div>
    <div style="display:flex;gap:.75rem;margin-top:1.5rem">
      <button type="submit" class="btn btn-primary">Cr&eacute;er et g&eacute;n&eacute;rer le PDF</button>
      <a href="/pages/receipts.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>

<script>
const tenantData = <?= json_encode(array_map(fn($t) => [
    'id'      => (int)$t['id'],
    'flat_id' => (int)$t['flat_id'],
    'name'    => $t['first_name'] . ' ' . $t['last_name'],
], $all_tenants), JSON_HEX_TAG) ?>;
const preselectedTenant = <?= (int)$form['tenant_id'] ?>;

function filterTenants() {
    const flatId   = parseInt(document.getElementById('flat_id').value) || 0;
    const sel      = document.getElementById('tenant_id');
    const filtered = tenantData.filter(t => !flatId || t.flat_id === flatId);
    sel.innerHTML  = '<option value="">— Choisir —</option>';
    filtered.forEach(t => {
        const opt = document.createElement('option');
        opt.value       = t.id;
        opt.textContent = t.name;
        if (t.id === preselectedTenant) opt.selected = true;
        sel.appendChild(opt);
    });
    // Auto-sélection si un seul locataire pour cet appartement
    if (flatId && filtered.length === 1 && !preselectedTenant) {
        sel.value = filtered[0].id;
    }
}
filterTenants();
</script>

<?php endif ?>

<?php require_once __DIR__ . '/../includes/footer.php' ?>
