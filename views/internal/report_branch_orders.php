<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/company.php';
require_once __DIR__ . '/../../app/db.php';

$user = internal_require_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner', 'Sub Branch'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$branchId = (int) ($_GET['branch_id'] ?? 0);
if ($role === 'Sub Branch') {
    $branchId = (int) ($user['branch_id'] ?? 0);
}
if ($branchId <= 0) {
    http_response_code(400);
    echo 'branch_id is required.';
    exit;
}

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($dateFrom === '') {
    $dateFrom = date('Y-m-01');
}
if ($dateTo === '') {
    $dateTo = date('Y-m-t');
}
if ($dateFrom !== '' && strtotime($dateFrom) === false) {
    http_response_code(400);
    echo 'Invalid date_from.';
    exit;
}
if ($dateTo !== '' && strtotime($dateTo) === false) {
    http_response_code(400);
    echo 'Invalid date_to.';
    exit;
}

$branchStmt = db()->prepare('SELECT name FROM branches WHERE id = ? AND deleted_at IS NULL');
$branchStmt->execute([$branchId]);
$branchName = (string) ($branchStmt->fetchColumn() ?: '');
if ($branchName === '') {
    http_response_code(404);
    echo 'Branch not found.';
    exit;
}

$where = ['o.deleted_at IS NULL', 'o.sub_branch_id = ?'];
$params = [$branchId];
if ($dateFrom !== '') {
    $where[] = 'DATE(o.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(o.created_at) <= ?';
    $params[] = $dateTo;
}

$sql = 'SELECT o.id, o.tracking_number, o.fulfillment_status, o.total_price, o.created_at, '
    . 's.shipment_number '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY o.created_at DESC, o.id DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$statusLabels = [
    'in_shipment' => 'In shipment',
    'main_branch' => 'In main branch',
    'received_subbranch' => 'Received at sub branch',
];

$grouped = [
    'in_shipment' => [],
    'main_branch' => [],
    'received_subbranch' => [],
];
$totals = [
    'in_shipment' => 0.0,
    'main_branch' => 0.0,
    'received_subbranch' => 0.0,
];

foreach ($rows as $row) {
    $status = $row['fulfillment_status'] ?? '';
    if (!isset($grouped[$status])) {
        continue;
    }
    $grouped[$status][] = $row;
    $totals[$status] += (float) ($row['total_price'] ?? 0);
}

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$periodLabel = $dateFrom && $dateTo ? "{$dateFrom} to {$dateTo}" : 'All dates';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Branch Orders Report</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; padding: 32px; font-family: "Georgia", "Times New Roman", serif; color: #1b1b1b; background: #f8f7f5; }
        .sheet { max-width: 1040px; margin: 0 auto; border: 1px solid #ddd; padding: 32px; background: #fff; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #222; padding-bottom: 16px; }
        .brand { display: flex; gap: 16px; align-items: center; }
        .brand img { width: 72px; height: 72px; object-fit: contain; }
        .brand h1 { margin: 0; font-size: 22px; letter-spacing: 1px; text-transform: uppercase; }
        .company-meta { font-size: 12px; line-height: 1.5; color: #333; }
        .report-meta { text-align: right; font-size: 12px; color: #555; }
        .section { margin-top: 24px; }
        .section h3 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 12px; }
        .summary-card { border: 1px solid #e1e1e1; padding: 12px; }
        .summary-card strong { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; border-bottom: 1px solid #ccc; padding: 8px 6px; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; color: #555; }
        td { padding: 8px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .actions { margin-top: 24px; text-align: right; }
        .actions button { padding: 8px 14px; border: 1px solid #222; background: #222; color: #fff; cursor: pointer; }
        @media print {
            body { padding: 0; background: #fff; }
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
        <div class="report-meta">
            <div><strong>Branch Orders Report</strong></div>
            <div>Branch: <?= $escape($branchName) ?></div>
            <div>Period: <?= $escape($periodLabel) ?></div>
        </div>
    </header>

    <section class="section">
        <div class="summary-grid">
            <?php foreach ($statusLabels as $status => $label): ?>
                <div class="summary-card">
                    <div><?= $escape($label) ?></div>
                    <strong><?= number_format(count($grouped[$status])) ?> | <?= number_format($totals[$status], 2) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php foreach ($statusLabels as $status => $label): ?>
        <section class="section">
            <h3><?= $escape($label) ?></h3>
            <?php if (empty($grouped[$status])): ?>
                <p>No orders found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Shipment</th>
                            <th>Tracking</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped[$status] as $row): ?>
                            <?php $dateLabel = $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : ''; ?>
                            <tr>
                                <td><?= $escape($dateLabel ?: '-') ?></td>
                                <td><?= $escape($row['shipment_number'] ?? '-') ?></td>
                                <td><?= $escape($row['tracking_number'] ?? '-') ?></td>
                                <td><?= $escape($label) ?></td>
                                <td><?= number_format((float) ($row['total_price'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>
</body>
</html>
