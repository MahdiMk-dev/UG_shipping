<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/company.php';

$user = internal_require_user();
if (!in_array($user['role'] ?? '', ['Admin', 'Owner', 'Main Branch', 'Warehouse'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$invoiceId = (int) ($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    http_response_code(400);
    echo 'Invoice ID is required.';
    exit;
}

$stmt = db()->prepare(
    'SELECT i.id, i.invoice_no, i.status, i.total, i.paid_total, i.due_total, i.issued_at, i.note, '
    . 'i.shipment_id, s.shipment_number, c.name AS origin_country, '
    . 'p.name AS partner_name, p.phone AS partner_phone, p.address AS partner_address, p.type AS partner_type '
    . 'FROM partner_invoices i '
    . 'JOIN partner_profiles p ON p.id = i.partner_id '
    . 'LEFT JOIN shipments s ON s.id = i.shipment_id '
    . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
    . 'WHERE i.id = ? AND i.deleted_at IS NULL'
);
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();
if (!$invoice) {
    http_response_code(404);
    echo 'Invoice not found.';
    exit;
}

$company = company_settings();

$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$issuedAt = $invoice['issued_at'] ? date('Y-m-d H:i', strtotime($invoice['issued_at'])) : '';
$partnerType = $invoice['partner_type'] === 'consignee' ? 'Consignee' : 'Shipper';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner Invoice <?= $escape($invoice['invoice_no']) ?></title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; padding: 32px; font-family: "Georgia", "Times New Roman", serif; color: #1b1b1b; }
        .sheet { max-width: 820px; margin: 0 auto; border: 1px solid #ddd; padding: 32px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #222; padding-bottom: 16px; }
        .brand { display: flex; gap: 16px; align-items: center; }
        .brand img { width: 80px; height: 80px; object-fit: contain; }
        .brand h1 { margin: 0; font-size: 24px; letter-spacing: 1px; text-transform: uppercase; }
        .company-meta { font-size: 13px; line-height: 1.5; color: #333; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 26px; letter-spacing: 2px; }
        .doc-title span { display: block; font-size: 12px; color: #555; margin-top: 4px; }
        .section { margin-top: 24px; display: flex; gap: 24px; }
        .section .block { flex: 1; border: 1px solid #e1e1e1; padding: 16px; }
        .block h3 { margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .block p { margin: 4px 0; font-size: 14px; }
        .line-items { margin-top: 24px; border-top: 2px solid #222; padding-top: 16px; }
        .totals { margin-left: auto; max-width: 280px; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 6px 0; font-size: 14px; }
        .totals tr:last-child td { font-weight: bold; border-top: 1px solid #999; }
        .notes { margin-top: 24px; border-top: 1px dashed #999; padding-top: 12px; font-size: 13px; }
        .actions { margin-top: 24px; text-align: right; }
        .actions button { padding: 8px 14px; border: 1px solid #222; background: #222; color: #fff; cursor: pointer; }
        @media print {
            body { padding: 0; }
            .sheet { border: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <header class="header">
        <div class="brand">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="<?= $escape($company['logo_url']) ?>" alt="<?= $escape($company['name']) ?>">
            <?php endif; ?>
            <div>
                <h1><?= $escape($company['name']) ?></h1>
                <div class="company-meta">
                    <?php if (!empty($company['address'])): ?><?= $escape($company['address']) ?><br><?php endif; ?>
                    <?php if (!empty($company['phone'])): ?><?= $escape($company['phone']) ?><br><?php endif; ?>
                    <?php if (!empty($company['email'])): ?><?= $escape($company['email']) ?><br><?php endif; ?>
                    <?php if (!empty($company['website'])): ?><?= $escape($company['website']) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="doc-title">
            <h2>INVOICE</h2>
            <span>#<?= $escape($invoice['invoice_no']) ?></span>
            <span><?= $escape($issuedAt) ?></span>
            <span>Status: <?= $escape($invoice['status']) ?></span>
        </div>
    </header>

    <section class="section">
        <div class="block">
            <h3>Billed To</h3>
            <p><strong><?= $escape($invoice['partner_name']) ?></strong></p>
            <p>Type: <?= $escape($partnerType) ?></p>
            <?php if (!empty($invoice['partner_phone'])): ?><p>Phone: <?= $escape($invoice['partner_phone']) ?></p><?php endif; ?>
            <?php if (!empty($invoice['partner_address'])): ?><p><?= $escape($invoice['partner_address']) ?></p><?php endif; ?>
        </div>
        <div class="block">
            <h3>Shipment</h3>
            <?php if (!empty($invoice['shipment_number'])): ?>
                <p>Shipment #: <?= $escape($invoice['shipment_number']) ?></p>
                <?php if (!empty($invoice['origin_country'])): ?><p>Origin: <?= $escape($invoice['origin_country']) ?></p><?php endif; ?>
            <?php else: ?>
                <p>No shipment linked.</p>
            <?php endif; ?>
        </div>
    </section>

    <div class="line-items">
        <div class="totals">
            <table>
                <tr>
                    <td>Total</td>
                    <td style="text-align:right;"><?= number_format((float) $invoice['total'], 2) ?></td>
                </tr>
                <tr>
                    <td>Paid</td>
                    <td style="text-align:right;"><?= number_format((float) $invoice['paid_total'], 2) ?></td>
                </tr>
                <tr>
                    <td>Due</td>
                    <td style="text-align:right;"><?= number_format((float) $invoice['due_total'], 2) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php if (!empty($invoice['note'])): ?>
        <div class="notes">
            <strong>Notes:</strong> <?= $escape($invoice['note']) ?>
        </div>
    <?php endif; ?>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>
</body>
</html>
