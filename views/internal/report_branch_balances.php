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
    if ($branchId <= 0) {
        http_response_code(403);
        echo 'Branch scope required.';
        exit;
    }
}

$branchName = '';
if ($branchId > 0) {
    $branchStmt = db()->prepare('SELECT name FROM branches WHERE id = ? AND deleted_at IS NULL');
    $branchStmt->execute([$branchId]);
    $branchName = (string) ($branchStmt->fetchColumn() ?: '');
    if ($branchName === '') {
        http_response_code(404);
        echo 'Branch not found.';
        exit;
    }
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

$where = ['1=1'];
$params = [];
if ($branchId > 0) {
    $where[] = 'e.branch_id = ?';
    $params[] = $branchId;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(e.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(e.created_at) <= ?';
    $params[] = $dateTo;
}

$summarySql = 'SELECT e.branch_id, b.name AS branch_name, '
    . 'SUM(CASE WHEN e.amount > 0 THEN e.amount ELSE 0 END) AS total_in, '
    . 'SUM(CASE WHEN e.amount < 0 THEN -e.amount ELSE 0 END) AS total_out, '
    . 'SUM(e.amount) AS net_total, '
    . 'COUNT(*) AS entry_count '
    . 'FROM branch_balance_entries e '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'GROUP BY e.branch_id '
    . 'ORDER BY b.name ASC';
$summaryStmt = db()->prepare($summarySql);
$summaryStmt->execute($params);
$summaryRows = $summaryStmt->fetchAll();

$detailsSql = 'SELECT e.id, e.branch_id, b.name AS branch_name, e.entry_type, e.amount, e.reference_type, '
    . 'e.reference_id, e.note, e.created_at, '
    . 'o.tracking_number, s.shipment_number, '
    . 't.from_branch_id, bf.name AS from_branch_name, '
    . 't.to_branch_id, bt.name AS to_branch_name '
    . 'FROM branch_balance_entries e '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'LEFT JOIN orders o ON o.id = e.reference_id AND e.reference_type = \'order\' '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN branch_transfers t ON t.id = e.reference_id AND e.reference_type = \'branch_transfer\' '
    . 'LEFT JOIN branches bf ON bf.id = t.from_branch_id '
    . 'LEFT JOIN branches bt ON bt.id = t.to_branch_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY e.created_at DESC, e.id DESC';
$detailsStmt = db()->prepare($detailsSql);
$detailsStmt->execute($params);
$details = $detailsStmt->fetchAll();

$entryLabels = [
    'order_received' => 'Order received',
    'order_reversal' => 'Order reversal',
    'transfer_out' => 'Transfer out',
    'transfer_in' => 'Transfer in',
    'adjustment' => 'Adjustment',
];

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$periodLabel = $dateFrom && $dateTo ? "{$dateFrom} to {$dateTo}" : 'All dates';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Branch Balance Report</title>
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
        .amount-positive { color: #0a7d33; }
        .amount-negative { color: #b21a1a; }
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
            <div><strong>Branch Balance Report</strong></div>
            <div>Period: <?= $escape($periodLabel) ?></div>
            <?php if ($branchId > 0): ?>
                <div>Branch: <?= $escape($branchName ?: '-') ?></div>
            <?php else: ?>
                <div>Company-wide</div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($summaryRows)): ?>
        <section class="section">
            <div class="summary-grid">
                <?php
                $totalIn = 0.0;
                $totalOut = 0.0;
                $totalNet = 0.0;
                $totalCount = 0;
                foreach ($summaryRows as $row) {
                    $totalIn += (float) ($row['total_in'] ?? 0);
                    $totalOut += (float) ($row['total_out'] ?? 0);
                    $totalNet += (float) ($row['net_total'] ?? 0);
                    $totalCount += (int) ($row['entry_count'] ?? 0);
                }
                ?>
                <div class="summary-card">
                    <div>Total entries</div>
                    <strong><?= number_format($totalCount) ?></strong>
                </div>
                <div class="summary-card">
                    <div>Total in</div>
                    <strong><?= number_format($totalIn, 2) ?></strong>
                </div>
                <div class="summary-card">
                    <div>Total out</div>
                    <strong><?= number_format($totalOut, 2) ?></strong>
                </div>
                <div class="summary-card">
                    <div>Net change</div>
                    <strong><?= number_format($totalNet, 2) ?></strong>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section">
        <h3>Summary by branch</h3>
        <?php if (empty($summaryRows)): ?>
            <p>No balance entries found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Entries</th>
                        <th>Total in</th>
                        <th>Total out</th>
                        <th>Net change</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaryRows as $row): ?>
                        <tr>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= number_format((int) ($row['entry_count'] ?? 0)) ?></td>
                            <td><?= number_format((float) ($row['total_in'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['total_out'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['net_total'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Balance entries</h3>
        <?php if (empty($details)): ?>
            <p>No balance entries found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $row): ?>
                        <?php
                        $dateLabel = $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '';
                        $entryType = $row['entry_type'] ?? '';
                        $typeLabel = $entryLabels[$entryType] ?? ($entryType ?: '-');
                        $amount = (float) ($row['amount'] ?? 0);
                        $amountClass = $amount >= 0 ? 'amount-positive' : 'amount-negative';
                        $reference = '-';
                        if (($row['reference_type'] ?? '') === 'order') {
                            $reference = trim(($row['shipment_number'] ?? '') . ' ' . ($row['tracking_number'] ?? ''));
                            $reference = $reference !== '' ? $reference : 'Order #' . ($row['reference_id'] ?? '');
                        } elseif (($row['reference_type'] ?? '') === 'branch_transfer') {
                            $reference = trim(($row['from_branch_name'] ?? '-') . ' -> ' . ($row['to_branch_name'] ?? '-'));
                        } elseif (!empty($row['reference_id'])) {
                            $reference = ($row['reference_type'] ?? 'Ref') . ' #' . $row['reference_id'];
                        }
                        ?>
                        <tr>
                            <td><?= $escape($dateLabel ?: '-') ?></td>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= $escape($typeLabel) ?></td>
                            <td><?= $escape($reference) ?></td>
                            <td class="<?= $amountClass ?>"><?= number_format($amount, 2) ?></td>
                            <td><?= $escape($row['note'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</div>
</body>
</html>
