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

$receiptId = (int) ($_GET['id'] ?? 0);
if ($receiptId <= 0) {
    http_response_code(400);
    echo 'Receipt ID is required.';
    exit;
}

$stmt = db()->prepare(
    'SELECT t.id, t.type, t.amount, t.payment_date, t.reason, t.note, t.created_at, '
    . 'pm.name AS payment_method, i.invoice_no, '
    . 'af.name AS from_account_name, aa.name AS to_account_name, '
    . 'p.name AS partner_name, p.phone AS partner_phone, p.address AS partner_address, p.type AS partner_type '
    . 'FROM partner_transactions t '
    . 'JOIN partner_profiles p ON p.id = t.partner_id '
    . 'LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id '
    . 'LEFT JOIN partner_invoices i ON i.id = t.invoice_id '
    . 'LEFT JOIN account_transfers at ON at.id = t.account_transfer_id '
    . 'LEFT JOIN accounts af ON af.id = at.from_account_id '
    . 'LEFT JOIN accounts aa ON aa.id = at.to_account_id '
    . 'WHERE t.id = ? AND t.deleted_at IS NULL'
);
$stmt->execute([$receiptId]);
$receipt = $stmt->fetch();
if (!$receipt) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}

$itemsStmt = db()->prepare(
    'SELECT description, amount FROM partner_transaction_items WHERE transaction_id = ? ORDER BY id ASC'
);
$itemsStmt->execute([$receiptId]);
$items = $itemsStmt->fetchAll();

$company = company_settings();

$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$dateValue = $receipt['payment_date'] ?: $receipt['created_at'];
$dateLabel = $dateValue ? date('Y-m-d H:i', strtotime($dateValue)) : '';
$partnerType = $receipt['partner_type'] === 'consignee' ? 'Consignee' : 'Shipper';
$title = strtoupper((string) ($receipt['type'] ?? 'receipt'));
$fromAccountName = (string) ($receipt['from_account_name'] ?? '');
$toAccountName = (string) ($receipt['to_account_name'] ?? '');
$accountLabel = '-';
if ($fromAccountName !== '' || $toAccountName !== '') {
    $accountLabel = ($fromAccountName !== '' ? $fromAccountName : '-')
        . ' -> '
        . ($toAccountName !== '' ? $toAccountName : '-');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner Receipt <?= $escape((string) $receipt['id']) ?></title>
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
        .doc-title h2 { margin: 0; font-size: 24px; letter-spacing: 2px; }
        .doc-title span { display: block; font-size: 12px; color: #555; margin-top: 4px; }
        .section { margin-top: 24px; display: flex; gap: 24px; }
        .section .block { flex: 1; border: 1px solid #e1e1e1; padding: 16px; }
        .block h3 { margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .block p { margin: 4px 0; font-size: 14px; }
        .line-items { margin-top: 24px; border-top: 2px solid #222; padding-top: 16px; }
        .line-items table { width: 100%; border-collapse: collapse; }
        .line-items th, .line-items td { padding: 8px 6px; font-size: 14px; border-bottom: 1px solid #e1e1e1; }
        .line-items th { text-align: left; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        .line-items td.amount { text-align: right; }
        .total { margin-top: 12px; text-align: right; font-size: 18px; }
        .notes { margin-top: 20px; border-top: 1px dashed #999; padding-top: 12px; font-size: 13px; }
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
            <h2><?= $escape($title) ?></h2>
            <span>#<?= $escape((string) $receipt['id']) ?></span>
            <span><?= $escape($dateLabel) ?></span>
        </div>
    </header>

    <section class="section">
        <div class="block">
            <h3>Received From</h3>
            <p><strong><?= $escape($receipt['partner_name']) ?></strong></p>
            <p>Type: <?= $escape($partnerType) ?></p>
            <?php if (!empty($receipt['partner_phone'])): ?><p>Phone: <?= $escape($receipt['partner_phone']) ?></p><?php endif; ?>
            <?php if (!empty($receipt['partner_address'])): ?><p><?= $escape($receipt['partner_address']) ?></p><?php endif; ?>
        </div>
        <div class="block">
            <h3>Payment Details</h3>
            <p>Account: <?= $escape($accountLabel) ?></p>
            <p>Method: <?= $escape($receipt['payment_method'] ?? '-') ?></p>
            <?php if (!empty($receipt['invoice_no'])): ?><p>Invoice: <?= $escape($receipt['invoice_no']) ?></p><?php endif; ?>
            <?php if (!empty($receipt['reason'])): ?><p>Reason: <?= $escape($receipt['reason']) ?></p><?php endif; ?>
        </div>
    </section>

    <div class="line-items">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= $escape($item['description']) ?></td>
                            <td class="amount"><?= number_format((float) $item['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">No line items recorded.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="total">
            Total: <?= number_format((float) $receipt['amount'], 2) ?>
        </div>
    </div>

    <?php if (!empty($receipt['note'])): ?>
        <div class="notes">
            <strong>Notes:</strong> <?= $escape($receipt['note']) ?>
        </div>
    <?php endif; ?>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>
</body>
</html>
