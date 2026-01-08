<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/company.php';

$user = internal_require_user();
if (!in_array($user['role'] ?? '', ['Admin', 'Owner'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$transferId = (int) ($_GET['id'] ?? 0);
if ($transferId <= 0) {
    http_response_code(400);
    echo 'Transfer ID is required.';
    exit;
}

$stmt = db()->prepare(
    'SELECT t.id, t.amount, t.transfer_date, t.note, t.created_at, '
    . 'bf.name AS from_branch_name, bt.name AS to_branch_name, '
    . 'u.name AS created_by_name '
    . 'FROM branch_transfers t '
    . 'LEFT JOIN branches bf ON bf.id = t.from_branch_id '
    . 'LEFT JOIN branches bt ON bt.id = t.to_branch_id '
    . 'LEFT JOIN users u ON u.id = t.created_by_user_id '
    . 'WHERE t.id = ? AND t.deleted_at IS NULL'
);
$stmt->execute([$transferId]);
$transfer = $stmt->fetch();
if (!$transfer) {
    http_response_code(404);
    echo 'Transfer not found.';
    exit;
}

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$dateValue = $transfer['transfer_date'] ?: ($transfer['created_at'] ? date('Y-m-d', strtotime($transfer['created_at'])) : '');
$printedAt = date('Y-m-d H:i');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Branch Payment Receipt <?= $escape((string) $transfer['id']) ?></title>
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
            <h2>Branch Payment</h2>
            <span>#<?= $escape((string) $transfer['id']) ?></span>
            <span><?= $escape((string) $dateValue) ?></span>
        </div>
    </header>

    <section class="section">
        <div class="block">
            <h3>From Branch</h3>
            <p><strong><?= $escape($transfer['from_branch_name'] ?? '-') ?></strong></p>
        </div>
        <div class="block">
            <h3>To Branch</h3>
            <p><strong><?= $escape($transfer['to_branch_name'] ?? '-') ?></strong></p>
            <?php if (!empty($transfer['created_by_name'])): ?><p>Recorded by: <?= $escape($transfer['created_by_name']) ?></p><?php endif; ?>
            <p>Printed by: <?= $escape($user['name'] ?? '-') ?></p>
            <p>Printed at: <?= $escape($printedAt) ?></p>
        </div>
    </section>

    <div class="amount">
        Amount: <?= number_format((float) ($transfer['amount'] ?? 0), 2) ?>
    </div>

    <?php if (!empty($transfer['note'])): ?>
        <div class="notes">
            <strong>Notes:</strong> <?= $escape($transfer['note']) ?>
        </div>
    <?php endif; ?>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>
</body>
</html>
