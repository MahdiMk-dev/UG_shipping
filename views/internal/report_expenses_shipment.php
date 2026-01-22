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

$shipmentId = (int) ($_GET['shipment_id'] ?? 0);
$originCountryId = (int) ($_GET['origin_country_id'] ?? 0);
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

$where = ['e.deleted_at IS NULL', 'e.shipment_id IS NOT NULL', 's.deleted_at IS NULL'];
$params = [];
if ($shipmentId > 0) {
    $where[] = 'e.shipment_id = ?';
    $params[] = $shipmentId;
}
if ($originCountryId > 0) {
    $where[] = 's.origin_country_id = ?';
    $params[] = $originCountryId;
}
if ($dateFrom !== '') {
    $where[] = 'COALESCE(e.expense_date, DATE(e.created_at)) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'COALESCE(e.expense_date, DATE(e.created_at)) <= ?';
    $params[] = $dateTo;
}

$sql = 'SELECT e.id, e.title, e.amount, e.expense_date, e.note, e.created_at, '
    . 'b.name AS branch_name, s.id AS shipment_id, s.shipment_number, '
    . 'c.name AS origin_country '
    . 'FROM general_expenses e '
    . 'JOIN shipments s ON s.id = e.shipment_id '
    . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY s.id DESC, COALESCE(e.expense_date, DATE(e.created_at)) DESC, e.id DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$originCountryName = '';
if ($originCountryId > 0) {
    $originStmt = db()->prepare('SELECT name FROM countries WHERE id = ?');
    $originStmt->execute([$originCountryId]);
    $originCountryName = (string) ($originStmt->fetchColumn() ?: '');
}

$shipments = [];
$grandTotal = 0.0;
foreach ($rows as $row) {
    $sid = (int) $row['shipment_id'];
    if (!isset($shipments[$sid])) {
        $shipments[$sid] = [
            'shipment_id' => $sid,
            'shipment_number' => $row['shipment_number'] ?? '',
            'origin_country' => $row['origin_country'] ?? '',
            'total' => 0.0,
            'items' => [],
        ];
    }
    $amount = (float) ($row['amount'] ?? 0);
    $shipments[$sid]['total'] += $amount;
    $shipments[$sid]['items'][] = $row;
    $grandTotal += $amount;
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
    <title>Shipment Expenses Report</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; padding: 32px; font-family: "Georgia", "Times New Roman", serif; color: #1b1b1b; background: #f8f7f5; }
        .sheet { max-width: 980px; margin: 0 auto; border: 1px solid #ddd; padding: 32px; background: #fff; }
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
        .shipment-block { margin-top: 18px; border: 1px solid #eee; padding: 12px; }
        .shipment-head { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; }
        .shipment-head h4 { margin: 0; font-size: 16px; }
        .shipment-head span { font-size: 12px; color: #555; }
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
            <div><strong>Shipment Expenses Report</strong></div>
            <div>Period: <?= $escape($periodLabel) ?></div>
            <?php if ($shipmentId > 0 && isset($shipments[$shipmentId])): ?>
                <div>Shipment #: <?= $escape($shipments[$shipmentId]['shipment_number']) ?></div>
            <?php endif; ?>
            <?php if ($originCountryId > 0): ?>
                <div>Origin: <?= $escape($originCountryName ?: 'Unknown') ?></div>
            <?php endif; ?>
        </div>
    </header>

    <section class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div>Total shipments</div>
                <strong><?= number_format(count($shipments)) ?></strong>
            </div>
            <div class="summary-card">
                <div>Total expenses</div>
                <strong><?= number_format($grandTotal, 2) ?></strong>
            </div>
        </div>
    </section>

    <section class="section">
        <h3>Shipment details</h3>
        <?php if (empty($shipments)): ?>
            <p>No shipment expenses found for this period.</p>
        <?php else: ?>
            <?php foreach ($shipments as $shipment): ?>
                <div class="shipment-block">
                    <div class="shipment-head">
                        <h4>Shipment #<?= $escape($shipment['shipment_number']) ?></h4>
                        <span>Origin: <?= $escape($shipment['origin_country'] ?: '-') ?> | Total: <?= number_format($shipment['total'], 2) ?></span>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Branch</th>
                                <th>Amount</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shipment['items'] as $item): ?>
                                <?php
                                $dateLabel = $item['expense_date'] ?: ($item['created_at'] ? date('Y-m-d', strtotime($item['created_at'])) : '');
                                ?>
                                <tr>
                                    <td><?= $escape($dateLabel ?: '-') ?></td>
                                    <td><?= $escape($item['title'] ?? '-') ?></td>
                                    <td><?= $escape($item['branch_name'] ?? '-') ?></td>
                                    <td><?= number_format((float) ($item['amount'] ?? 0), 2) ?></td>
                                    <td><?= $escape($item['note'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>
</body>
</html>
