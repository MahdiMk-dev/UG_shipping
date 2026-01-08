<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../app/company.php';

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

$dateWhere = ['t.deleted_at IS NULL', "t.status = 'active'"];
$dateParams = [];
if ($branchId > 0) {
    $dateWhere[] = 't.branch_id = ?';
    $dateParams[] = $branchId;
}
if ($dateFrom !== '') {
    $dateWhere[] = 'COALESCE(t.payment_date, DATE(t.created_at)) >= ?';
    $dateParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $dateWhere[] = 'COALESCE(t.payment_date, DATE(t.created_at)) <= ?';
    $dateParams[] = $dateTo;
}

$inTypes = ["payment", "deposit", "admin_settlement"];
$outTypes = ["refund", "adjustment"];

$summarySql = 'SELECT t.branch_id, b.name AS branch_name, '
    . 'SUM(CASE WHEN t.type IN (\'payment\',\'deposit\',\'admin_settlement\') THEN t.amount ELSE 0 END) AS total_in, '
    . 'SUM(CASE WHEN t.type IN (\'refund\',\'adjustment\') THEN t.amount ELSE 0 END) AS total_out, '
    . 'COUNT(*) AS tx_count '
    . 'FROM transactions t '
    . 'LEFT JOIN branches b ON b.id = t.branch_id '
    . 'WHERE ' . implode(' AND ', $dateWhere) . ' '
    . 'GROUP BY t.branch_id '
    . 'ORDER BY b.name ASC';
$summaryStmt = db()->prepare($summarySql);
$summaryStmt->execute($dateParams);
$summaryRows = $summaryStmt->fetchAll();

$branchName = '';
if ($branchId > 0) {
    $branchStmt = db()->prepare('SELECT name FROM branches WHERE id = ?');
    $branchStmt->execute([$branchId]);
    $branchName = (string) ($branchStmt->fetchColumn() ?: '');
}

$detailsSql = 'SELECT t.id, t.type, t.amount, t.payment_date, t.created_at, t.note, '
    . 'b.name AS branch_name, c.name AS customer_name, pm.name AS payment_method '
    . 'FROM transactions t '
    . 'LEFT JOIN branches b ON b.id = t.branch_id '
    . 'LEFT JOIN customers c ON c.id = t.customer_id '
    . 'LEFT JOIN payment_methods pm ON pm.id = t.payment_method_id '
    . 'WHERE ' . implode(' AND ', $dateWhere) . ' '
    . 'ORDER BY COALESCE(t.payment_date, DATE(t.created_at)) DESC, t.id DESC';
$detailsStmt = db()->prepare($detailsSql);
$detailsStmt->execute($dateParams);
$details = $detailsStmt->fetchAll();

$transferWhere = ['tr.deleted_at IS NULL'];
$transferParams = [];
if ($branchId > 0) {
    $transferWhere[] = '(tr.from_branch_id = ? OR tr.to_branch_id = ?)';
    $transferParams[] = $branchId;
    $transferParams[] = $branchId;
}
if ($dateFrom !== '') {
    $transferWhere[] = 'COALESCE(tr.transfer_date, DATE(tr.created_at)) >= ?';
    $transferParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $transferWhere[] = 'COALESCE(tr.transfer_date, DATE(tr.created_at)) <= ?';
    $transferParams[] = $dateTo;
}

$transferSql = 'SELECT tr.id, tr.from_branch_id, bf.name AS from_branch_name, '
    . 'tr.to_branch_id, bt.name AS to_branch_name, '
    . 'tr.amount, tr.transfer_date, tr.created_at, tr.note '
    . 'FROM branch_transfers tr '
    . 'LEFT JOIN branches bf ON bf.id = tr.from_branch_id '
    . 'LEFT JOIN branches bt ON bt.id = tr.to_branch_id '
    . 'WHERE ' . implode(' AND ', $transferWhere) . ' '
    . 'ORDER BY COALESCE(tr.transfer_date, DATE(tr.created_at)) DESC, tr.id DESC';
$transferStmt = db()->prepare($transferSql);
$transferStmt->execute($transferParams);
$transferDetails = $transferStmt->fetchAll();

$transferSummary = [];
$transferIn = 0.0;
$transferOut = 0.0;

foreach ($transferDetails as $row) {
    $amount = (float) ($row['amount'] ?? 0);
    $fromId = (int) ($row['from_branch_id'] ?? 0);
    $toId = (int) ($row['to_branch_id'] ?? 0);

    if ($branchId > 0) {
        if ($fromId === $branchId) {
            $transferOut += $amount;
        }
        if ($toId === $branchId) {
            $transferIn += $amount;
        }
    } else {
        $transferOut += $amount;
        $transferIn += $amount;
    }

    if ($fromId > 0) {
        if (!isset($transferSummary[$fromId])) {
            $transferSummary[$fromId] = [
                'branch_id' => $fromId,
                'branch_name' => $row['from_branch_name'] ?? '-',
                'total_in' => 0.0,
                'total_out' => 0.0,
                'tx_count' => 0,
            ];
        }
        $transferSummary[$fromId]['total_out'] += $amount;
        $transferSummary[$fromId]['tx_count'] += 1;
    }
    if ($toId > 0) {
        if (!isset($transferSummary[$toId])) {
            $transferSummary[$toId] = [
                'branch_id' => $toId,
                'branch_name' => $row['to_branch_name'] ?? '-',
                'total_in' => 0.0,
                'total_out' => 0.0,
                'tx_count' => 0,
            ];
        }
        $transferSummary[$toId]['total_in'] += $amount;
        $transferSummary[$toId]['tx_count'] += 1;
    }
}

$transferSummaryRows = array_values($transferSummary);
if ($branchId > 0) {
    $transferSummaryRows = array_values(array_filter(
        $transferSummaryRows,
        static fn($row) => (int) ($row['branch_id'] ?? 0) === $branchId
    ));
}
usort(
    $transferSummaryRows,
    static fn($a, $b) => strcmp((string) ($a['branch_name'] ?? ''), (string) ($b['branch_name'] ?? ''))
);

$customerIn = 0.0;
$customerOut = 0.0;
foreach ($summaryRows as $row) {
    $customerIn += (float) ($row['total_in'] ?? 0);
    $customerOut += (float) ($row['total_out'] ?? 0);
}

$totalIn = $customerIn + $transferIn;
$totalOut = $customerOut + $transferOut;

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$periodLabel = $dateFrom && $dateTo ? "{$dateFrom} to {$dateTo}" : 'All dates';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transactions Report</title>
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
            <div><strong>Transactions Report</strong></div>
            <div>Period: <?= $escape($periodLabel) ?></div>
            <?php if ($branchId > 0): ?>
                <div>Branch scope: <?= $escape($branchName ?: '-') ?></div>
            <?php else: ?>
                <div>Company-wide</div>
            <?php endif; ?>
        </div>
    </header>

    <section class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div>Customer in</div>
                <strong><?= number_format($customerIn, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Customer out</div>
                <strong><?= number_format($customerOut, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Transfer in</div>
                <strong><?= number_format($transferIn, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Transfer out</div>
                <strong><?= number_format($transferOut, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Total in</div>
                <strong><?= number_format($totalIn, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Total out</div>
                <strong><?= number_format($totalOut, 2) ?></strong>
            </div>
        </div>
    </section>

    <section class="section">
        <h3>Customer transactions by branch</h3>
        <?php if (empty($summaryRows)): ?>
            <p>No transactions found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Transactions</th>
                        <th>Total in</th>
                        <th>Total out</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaryRows as $row): ?>
                        <tr>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= number_format((int) ($row['tx_count'] ?? 0)) ?></td>
                            <td><?= number_format((float) ($row['total_in'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['total_out'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Branch transfers summary</h3>
        <?php if (empty($transferSummaryRows)): ?>
            <p>No branch transfers found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Transfers</th>
                        <th>Total in</th>
                        <th>Total out</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transferSummaryRows as $row): ?>
                        <tr>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= number_format((int) ($row['tx_count'] ?? 0)) ?></td>
                            <td><?= number_format((float) ($row['total_in'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['total_out'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Branch transfer details</h3>
        <?php if (empty($transferDetails)): ?>
            <p>No branch transfer details found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>From branch</th>
                        <th>To branch</th>
                        <th>Amount</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transferDetails as $row): ?>
                        <?php
                        $dateLabel = $row['transfer_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                        ?>
                        <tr>
                            <td><?= $escape($dateLabel ?: '-') ?></td>
                            <td><?= $escape($row['from_branch_name'] ?? '-') ?></td>
                            <td><?= $escape($row['to_branch_name'] ?? '-') ?></td>
                            <td><?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                            <td><?= $escape($row['note'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Customer transaction details</h3>
        <?php if (empty($details)): ?>
            <p>No transaction details found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Payment method</th>
                        <th>Amount</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $row): ?>
                        <?php
                        $dateLabel = $row['payment_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                        $direction = in_array($row['type'], $inTypes, true) ? 'In' : 'Out';
                        $typeLabel = $row['type'] ? ucfirst(str_replace('_', ' ', (string) $row['type'])) : '-';
                        ?>
                        <tr>
                            <td><?= $escape($dateLabel ?: '-') ?></td>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= $escape($row['customer_name'] ?? '-') ?></td>
                            <td><?= $escape($typeLabel . ' (' . $direction . ')') ?></td>
                            <td><?= $escape($row['payment_method'] ?? '-') ?></td>
                            <td><?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
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
