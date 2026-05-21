<?php
// pdf/generate_receipt.php — Génération PDF de la quittance (TCPDF)

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

// TCPDF doit être installé via Composer : composer require tecnickcom/tcpdf
// ou déposé manuellement dans /vendor/tcpdf/
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/tcpdf/tcpdf.php')) {
    require_once __DIR__ . '/../vendor/tcpdf/tcpdf.php';
} else {
    die('TCPDF introuvable. Lancez : composer require tecnickcom/tcpdf');
}

/**
 * Génère une quittance de loyer au format PDF.
 *
 * @param array $data {
 *   landlord_name, landlord_address,
 *   tenant_name, flat_address,
 *   period_month (int), period_year (int),
 *   rent_amount (float), charges_amount (float), total_amount (float),
 *   payment_date (Y-m-d), payment_mode,
 *   notes (optional)
 * }
 * @param string $output  'F' = fichier, 'I' = navigateur, 'D' = téléchargement, 'S' = string
 * @param string $filename
 * @return string|null  Chemin du fichier si output='F', sinon null
 */
function generate_receipt_pdf(array $data, string $output = 'F', string $filename = ''): ?string {

    $months_fr = [
        1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',
        7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre',
    ];
    $period_label = ($months_fr[$data['period_month']] ?? '?') . ' ' . $data['period_year'];

    if (empty($filename)) {
        $filename = sprintf(
            'quittance_%04d_%02d_%s.pdf',
            $data['period_year'],
            $data['period_month'],
            preg_replace('/[^a-z0-9]/', '_', strtolower($data['tenant_name']))
        );
    }

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Meta
    $pdf->SetCreator(APP_NAME);
    $pdf->SetAuthor($data['landlord_name']);
    $pdf->SetTitle('Quittance de loyer – ' . $period_label);
    $pdf->SetSubject('Quittance de loyer');
    $pdf->SetKeywords('quittance loyer bail');

    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->AddPage();

    // ── En-tête ────────────────────────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell(0, 10, 'QUITTANCE DE LOYER', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 7, 'Période : ' . $period_label, 0, 1, 'C');
    $pdf->Ln(6);

    // ── Ligne de séparation ────────────────────────────────────────────────────
    $pdf->SetDrawColor(180, 180, 180);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(6);

    // ── Bailleur / Locataire ───────────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(30, 30, 30);

    $col_w = 80;
    $y_parties = $pdf->GetY();

    // Bailleur
    $bail_label = (($data['mandate_type'] ?? 'proprietaire') === 'mandataire') ? 'MANDATAIRE' : 'BAILLEUR';
    $pdf->SetXY(20, $y_parties);
    $pdf->Cell($col_w, 6, $bail_label, 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetX(20);
    $pdf->MultiCell($col_w, 5, $data['landlord_name'] . "\n" . $data['landlord_address'], 0, 'L');

    // Locataire
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(110, $y_parties);
    $pdf->Cell($col_w, 6, 'LOCATAIRE', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(110, $y_parties + 6);
    $pdf->MultiCell($col_w, 5, $data['tenant_name'], 0, 'L');

    $pdf->Ln(6);
    $y_after = $pdf->GetY() + 2;
    $pdf->SetY($y_after);

    // ── Logement ───────────────────────────────────────────────────────────────
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'LOGEMENT', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 5, $data['flat_address'], 0, 'L');
    $pdf->Ln(5);

    // ── Tableau des sommes ─────────────────────────────────────────────────────
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(120, 8, 'Désignation', 1, 0, 'L', true);
    $pdf->Cell(50,  8, 'Montant', 1, 1, 'R', true);

    $pdf->SetFont('helvetica', '', 10);
    $rows = [
        ['Loyer nu',         $data['rent_amount']],
        ['Charges locatives',$data['charges_amount']],
    ];
    foreach ($rows as $row) {
        $pdf->Cell(120, 7, $row[0], 1, 0, 'L');
        $pdf->Cell(50,  7, money($row[1]), 1, 1, 'R');
    }

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 235, 220);
    $pdf->Cell(120, 8, 'TOTAL REÇU', 1, 0, 'L', true);
    $pdf->Cell(50,  8, money($data['total_amount']), 1, 1, 'R', true);
    $pdf->Ln(5);

    // ── Paiement ───────────────────────────────────────────────────────────────
    $pdf->SetFont('helvetica', '', 10);
    $d = DateTime::createFromFormat('Y-m-d', $data['payment_date']);
    $date_fr = $d ? $d->format('d/m/Y') : $data['payment_date'];

    $is_mandataire = ($data['mandate_type'] ?? 'proprietaire') === 'mandataire';
    $signataire = $is_mandataire
        ? "Le mandataire soussigné, agissant au nom et pour le compte du propriétaire,"
        : "Le bailleur soussigné";

    $pdf->MultiCell(0, 6,
        $signataire . " déclare avoir reçu de " . $data['tenant_name'] .
        " la somme de " . money($data['total_amount']) .
        " au titre du loyer et des charges du logement ci-dessus désigné pour la période de " . $period_label .
        ", et lui en donne quittance, sous réserve de tous droits." .
        "\n\nDate de paiement : " . $date_fr .
        "\nMode de paiement : " . $data['payment_mode'],
        0, 'L'
    );

    // Notes éventuelles
    if (!empty($data['notes'])) {
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->MultiCell(0, 5, 'Note : ' . $data['notes'], 0, 'L');
    }

    // ── Signature ─────────────────────────────────────────────────────────────
    $pdf->Ln(15);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->SetFont('helvetica', '', 10);
    $sig_y = $pdf->GetY();
    $pdf->SetXY(110, $sig_y);
    $pdf->Cell(80, 5, 'Fait le ' . (new DateTime())->format('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(15);
    $pdf->SetXY(110, $pdf->GetY());
    $pdf->Cell(80, 5, $data['landlord_name'], 0, 1, 'R');
    $pdf->SetXY(110, $pdf->GetY());
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(80, 5, '(signature)', 0, 1, 'R');

    // ── Pied de page légal ─────────────────────────────────────────────────────
    $pdf->SetAutoPageBreak(false);
    $pdf->SetY(-25);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(2);
    $pdf->Cell(0, 4,
        'Cette quittance annule tous les reçus qui auraient pu être établis précédemment pour la même période.',
        0, 1, 'C');
    $pdf->Cell(0, 4,
        'Document généré par ' . APP_NAME,
        0, 1, 'C');

    // ── Sortie ────────────────────────────────────────────────────────────────
    if ($output === 'F') {
        $filepath = RECEIPTS_PATH . '/' . $filename;
        $pdf->Output($filepath, 'F');
        return $filepath;
    }

    $pdf->Output($filename, $output);
    return null;
}

/** Raccourci : renvoie le PDF directement au navigateur */
function stream_receipt_pdf(array $data, string $filename = ''): void {
    generate_receipt_pdf($data, 'I', $filename);
}
