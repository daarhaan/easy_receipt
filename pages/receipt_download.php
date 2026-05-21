<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_auth();
$uid  = $user['id'];

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('
    SELECT r.*,
        f.name AS flat_name, f.address AS flat_address,
        CONCAT(t.first_name," ",t.last_name) AS tenant_name,
        l.name AS landlord_name, l.address AS landlord_address
    FROM receipts r
    JOIN flats f ON f.id = r.flat_id
    JOIN tenants t ON t.id = r.tenant_id
    LEFT JOIN landlords l ON l.user_id = r.user_id
    WHERE r.id = ? AND r.user_id = ?
');
$stmt->execute([$id, $uid]);
$receipt = $stmt->fetch();

if (!$receipt) {
    flash('error', 'Quittance introuvable.');
    redirect('/pages/receipts.php');
}

// Streaming PDF
if (($_GET['action'] ?? '') === 'pdf') {
    if (empty($receipt['landlord_name'])) {
        flash('error', "Veuillez d'abord configurer votre profil bailleur pour générer le PDF.");
        redirect('/pages/profile.php');
    }

    require_once __DIR__ . '/../pdf/generate_receipt.php';

    $data = [
        'landlord_name'    => $receipt['landlord_name'],
        'landlord_address' => $receipt['landlord_address'],
        'tenant_name'      => $receipt['tenant_name'],
        'flat_address'     => $receipt['flat_address'],
        'period_month'     => (int)$receipt['period_month'],
        'period_year'      => (int)$receipt['period_year'],
        'rent_amount'      => (float)$receipt['rent_amount'],
        'charges_amount'   => (float)$receipt['charges_amount'],
        'total_amount'     => (float)$receipt['total_amount'],
        'payment_date'     => $receipt['payment_date'],
        'payment_mode'     => $receipt['payment_mode'],
        'notes'            => $receipt['notes'] ?? '',
    ];

    $filename = sprintf('quittance_%04d_%02d_%s.pdf',
        $data['period_year'],
        $data['period_month'],
        preg_replace('/[^a-z0-9]/', '_', strtolower($receipt['tenant_name']))
    );

    stream_receipt_pdf($data, $filename);
    exit;
}

// Page de visualisation
$page_title  = 'Quittance — ' . french_month((int)$receipt['period_month'], (int)$receipt['period_year']);
$current_nav = 'receipts';
$flash       = get_flash();
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif ?>

<div class="breadcrumb">
  <a href="/pages/receipts.php">Quittances</a>
  <span class="sep">&rsaquo;</span>
  <a href="/pages/flat_detail.php?id=<?= $receipt['flat_id'] ?>"><?= e($receipt['flat_name']) ?></a>
  <span class="sep">&rsaquo;</span>
  <?= e(french_month((int)$receipt['period_month'], (int)$receipt['period_year'])) ?>
</div>

<div class="page-header">
  <h1>Quittance &mdash; <?= e(french_month((int)$receipt['period_month'], (int)$receipt['period_year'])) ?></h1>
  <?php if ($receipt['landlord_name']): ?>
  <a href="?id=<?= $id ?>&action=pdf" class="btn btn-primary" target="_blank">&#128196; T&eacute;l&eacute;charger le PDF</a>
  <?php endif ?>
</div>

<div class="card" style="max-width:620px">
  <table>
    <tbody>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem;width:45%">Appartement</td>
          <td style="padding:.6rem 1rem"><strong><?= e($receipt['flat_name']) ?></strong></td></tr>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">Adresse</td>
          <td style="padding:.6rem 1rem"><?= nl2br(e($receipt['flat_address'])) ?></td></tr>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">Locataire</td>
          <td style="padding:.6rem 1rem"><?= e($receipt['tenant_name']) ?></td></tr>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">P&eacute;riode</td>
          <td style="padding:.6rem 1rem"><?= e(french_month((int)$receipt['period_month'], (int)$receipt['period_year'])) ?></td></tr>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">Loyer nu</td>
          <td style="padding:.6rem 1rem"><?= money((float)$receipt['rent_amount']) ?></td></tr>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">Charges</td>
          <td style="padding:.6rem 1rem"><?= money((float)$receipt['charges_amount']) ?></td></tr>
      <tr style="background:var(--sage-light)">
          <td style="color:var(--sage-dark);padding:.6rem 1rem;font-weight:600">Total</td>
          <td style="padding:.6rem 1rem;font-weight:600;color:var(--sage-dark)"><?= money((float)$receipt['total_amount']) ?></td></tr>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">Date paiement</td>
          <td style="padding:.6rem 1rem"><?= french_date($receipt['payment_date']) ?></td></tr>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">Mode paiement</td>
          <td style="padding:.6rem 1rem"><?= e($receipt['payment_mode']) ?></td></tr>
      <?php if ($receipt['notes']): ?>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">Notes</td>
          <td style="padding:.6rem 1rem"><?= e($receipt['notes']) ?></td></tr>
      <?php endif ?>
      <?php if ($receipt['landlord_name']): ?>
      <tr><td style="color:var(--ink-light);padding:.6rem 1rem">Bailleur</td>
          <td style="padding:.6rem 1rem"><?= e($receipt['landlord_name']) ?></td></tr>
      <?php endif ?>
    </tbody>
  </table>

  <?php if (empty($receipt['landlord_name'])): ?>
  <div class="alert alert-info" style="margin-top:1.5rem;margin-bottom:0">
    &#9888; Profil bailleur incomplet &mdash; le PDF ne peut pas &ecirc;tre g&eacute;n&eacute;r&eacute;.
    <a href="/pages/profile.php"><strong>Configurer maintenant</strong></a>
  </div>
  <?php endif ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php' ?>
