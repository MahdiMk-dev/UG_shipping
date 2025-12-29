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

$where = ['e.deleted_at IS NULL'];
$params = [];
if ($dateFrom !== '') {
    $where[] = 'COALESCE(e.expense_date, DATE(e.created_at)) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'COALESCE(e.expense_date, DATE(e.created_at)) <= ?';
    $params[] = $dateTo;
}

$generalSql = 'SELECT e.id, e.title, e.amount, e.expense_date, e.note, e.created_at, '
    . 'b.name AS branch_name, s.shipment_number '
    . 'FROM general_expenses e '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'LEFT JOIN shipments s ON s.id = e.shipment_id '
    . 'WHERE ' . implode(' AND ', $where) . ' '
    . 'ORDER BY COALESCE(e.expense_date, DATE(e.created_at)) DESC, e.id DESC';
$generalStmt = db()->prepare($generalSql);
$generalStmt->execute($params);
$generalRows = $generalStmt->fetchAll();

$staffWhere = ['e.deleted_at IS NULL'];
$staffParams = [];
if ($dateFrom !== '') {
    $staffWhere[] = 'COALESCE(e.expense_date, DATE(e.created_at)) >= ?';
    $staffParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $staffWhere[] = 'COALESCE(e.expense_date, DATE(e.created_at)) <= ?';
    $staffParams[] = $dateTo;
}
$staffWhere[] = "e.type IN ('advance', 'bonus', 'salary_payment')";

$staffSql = 'SELECT e.id, e.type, e.amount, e.expense_date, e.salary_month, e.note, e.created_at, '
    . 's.name AS staff_name, b.name AS branch_name '
    . 'FROM staff_expenses e '
    . 'LEFT JOIN staff_members s ON s.id = e.staff_id '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'WHERE ' . implode(' AND ', $staffWhere) . ' '
    . 'ORDER BY COALESCE(e.expense_date, DATE(e.created_at)) DESC, e.id DESC';
$staffStmt = db()->prepare($staffSql);
$staffStmt->execute($staffParams);
$staffRows = $staffStmt->fetchAll();

$generalTotal = 0.0;
foreach ($generalRows as $row) {
    $generalTotal += (float) ($row['amount'] ?? 0);
}
$staffTotal = 0.0;
foreach ($staffRows as $row) {
    $staffTotal += (float) ($row['amount'] ?? 0);
}
$grandTotal = $generalTotal + $staffTotal;

// Report mode: detailed (default) or brief
$mode = strtolower(trim((string)($_GET['mode'] ?? 'detailed')));
if (!in_array($mode, ['detailed', 'brief'], true)) {
    $mode = 'detailed';
}

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$periodLabel = $dateFrom && $dateTo ? "{$dateFrom} to {$dateTo}" : 'All dates';

$typeLabels = [
    'advance' => 'Advance',
    'bonus' => 'Bonus',
    'salary_payment' => 'Salary payment',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company Expenses Report</title>
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
            <div><strong>Company Expenses Report</strong></div>
            <div>Period: <?= $escape($periodLabel) ?></div>
        </div>
    </header>

    <div class="actions actions-top">
        <form method="get" style="display:inline-block; margin-right:12px;">
            <input type="hidden" name="date_from" value="<?= $escape($dateFrom) ?>">
            <input type="hidden" name="date_to" value="<?= $escape($dateTo) ?>">
            <label>View mode:
                <select name="mode" onchange="this.form.submit()">
                    <option value="detailed"<?= $mode==='detailed'?' selected':'' ?>>Detailed</option>
                    <option value="brief"<?= $mode==='brief'?' selected':'' ?>>Brief (monthly totals)</option>
                </select>
            </label>
        </form>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <section class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div>General expenses</div>
                <strong><?= number_format($generalTotal, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Staff expenses</div>
                <strong><?= number_format($staffTotal, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Total expenses</div>
                <strong><?= number_format($grandTotal, 2) ?></strong>
            </div>
        </div>
    </section>

    <section class="section">
        <h3>General expenses</h3>
        <?php if ($mode === 'brief'): ?>
            <?php
            $byMonth = [];
            foreach ($generalRows as $row) {
                $dt = $row['expense_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                $month = $dt ? date('Y-m', strtotime($dt)) : '';
                if (!$month) continue;
                if (!isset($byMonth[$month])) $byMonth[$month] = 0.0;
                $byMonth[$month] += (float)($row['amount'] ?? 0);
            }
            ksort($byMonth);
            ?>
            <table>
                <thead><tr><th>Month</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($byMonth as $month => $amt): ?>
                        <tr><td><?= $escape($month) ?></td><td><?= number_format($amt, 2) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php if (empty($generalRows)): ?>
                <p>No general expenses found for this period.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Shipment</th>
                            <th>Branch</th>
                            <th>Amount</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generalRows as $row): ?>
                            <?php
                            $dateLabel = $row['expense_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                            $shipmentLabel = $row['shipment_number'] ?: '-';
                            ?>
                            <tr>
                                <td><?= $escape($dateLabel ?: '-') ?></td>
                                <td><?= $escape($row['title'] ?? '-') ?></td>
                                <td><?= $escape($shipmentLabel) ?></td>
                                <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                                <td><?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                                <td><?= $escape($row['note'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Staff expenses</h3>
        <?php if ($mode === 'brief'): ?>
            <?php
            $byMonth = [];
            foreach ($staffRows as $row) {
                $dt = $row['expense_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                $month = $dt ? date('Y-m', strtotime($dt)) : '';
                if (!$month) continue;
                if (!isset($byMonth[$month])) $byMonth[$month] = 0.0;
                $byMonth[$month] += (float)($row['amount'] ?? 0);
            }
            ksort($byMonth);
            ?>
            <table>
                <thead><tr><th>Month</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($byMonth as $month => $amt): ?>
                        <tr><td><?= $escape($month) ?></td><td><?= number_format($amt, 2) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php if (empty($staffRows)): ?>
                <p>No staff expenses found for this period.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Staff</th>
                            <th>Type</th>
                            <th>Salary month</th>
                            <th>Branch</th>
                            <th>Amount</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffRows as $row): ?>
                            <?php
                            $dateLabel = $row['expense_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                            $typeLabel = $typeLabels[$row['type'] ?? ''] ?? ($row['type'] ?? '-');
                            $salaryMonth = $row['salary_month'] ? date('Y-m', strtotime($row['salary_month'])) : '-';
                            ?>
                            <tr>
                                <td><?= $escape($dateLabel ?: '-') ?></td>
                                <td><?= $escape($row['staff_name'] ?? '-') ?></td>
                                <td><?= $escape($typeLabel) ?></td>
                                <td><?= $escape($salaryMonth) ?></td>
                                <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                                <td><?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                                <td><?= $escape($row['note'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <style>
        .actions-top { margin-bottom: 18px; }
        @media print {
            .actions, .actions-top { display: none !important; }
        }
    </style>
</div>
</body>
</html>
