<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/company.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner', 'Main Branch', 'Sub Branch'], true)) {
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
    'SELECT t.id, t.type, t.amount, t.payment_date, t.note, t.created_at, t.branch_id, '
    . 'b.name AS branch_name, pm.name AS payment_method, '
    . 'c.name AS customer_name, c.code AS customer_code, c.phone AS customer_phone, c.address AS customer_address, '
    . 'cu.name AS created_by_name '
    . 'FROM transactions t '
    . 'JOIN customers c ON c.id = t.customer_id '
    . 'LEFT JOIN branches b ON b.id = t.branch_id '
    . 'LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id '
    . 'LEFT JOIN users cu ON cu.id = t.created_by_user_id '
    . 'WHERE t.id = ? AND t.deleted_at IS NULL'
);
$stmt->execute([$receiptId]);
$receipt = $stmt->fetch();
if (!$receipt) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}

if ($role === 'Sub Branch') {
    $userBranchId = (int) ($user['branch_id'] ?? 0);
    if ($userBranchId <= 0 || (int) $receipt['branch_id'] !== $userBranchId) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$allocStmt = db()->prepare(
    'SELECT i.invoice_no, ta.amount_allocated '
    . 'FROM transaction_allocations ta '
    . 'JOIN invoices i ON i.id = ta.invoice_id '
    . 'WHERE ta.transaction_id = ?'
);
$allocStmt->execute([$receiptId]);
$allocations = $allocStmt->fetchAll();

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$dateValue = $receipt['payment_date'] ?: $receipt['created_at'];
$dateLabel = $dateValue ? date('Y-m-d H:i', strtotime($dateValue)) : '';
$printedAt = date('Y-m-d H:i');
$title = strtoupper((string) ($receipt['type'] ?? 'payment'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaction Receipt <?= $escape((string) $receipt['id']) ?></title>
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
        .allocations { margin-top: 10px; font-size: 13px; }
        .allocations div { margin: 4px 0; }
        .amount { margin-top: 24px; border-top: 2px solid #222; padding-top: 16px; text-align: right; font-size: 20px; }
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
            <p><strong><?= $escape($receipt['customer_name']) ?></strong></p>
            <?php if (!empty($receipt['customer_code'])): ?><p>Code: <?= $escape($receipt['customer_code']) ?></p><?php endif; ?>
            <?php if (!empty($receipt['customer_phone'])): ?><p>Phone: <?= $escape($receipt['customer_phone']) ?></p><?php endif; ?>
            <?php if (!empty($receipt['customer_address'])): ?><p><?= $escape($receipt['customer_address']) ?></p><?php endif; ?>
        </div>
        <div class="block">
            <h3>Payment Details</h3>
            <p>Method: <?= $escape($receipt['payment_method'] ?? '-') ?></p>
            <p>Branch: <?= $escape($receipt['branch_name'] ?? '-') ?></p>
            <?php if (!empty($receipt['created_by_name'])): ?><p>Recorded by: <?= $escape($receipt['created_by_name']) ?></p><?php endif; ?>
            <p>Printed by: <?= $escape($user['name'] ?? '-') ?></p>
            <p>Printed at: <?= $escape($printedAt) ?></p>
            <?php if (!empty($allocations)): ?>
                <div class="allocations">
                    <strong>Invoice allocations</strong>
                    <?php foreach ($allocations as $allocation): ?>
                        <div>
                            <?= $escape($allocation['invoice_no']) ?> - <?= number_format((float) $allocation['amount_allocated'], 2) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="amount">
        Amount: <?= number_format((float) $receipt['amount'], 2) ?>
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
