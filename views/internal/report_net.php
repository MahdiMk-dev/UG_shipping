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


// Report mode: detailed (default) or brief
$mode = strtolower(trim((string)($_GET['mode'] ?? 'detailed')));
if (!in_array($mode, ['detailed', 'brief'], true)) {
    $mode = 'detailed';
}

$orderWhere = ['o.deleted_at IS NULL'];
$orderParams = [];
if ($dateFrom !== '') {
    $orderWhere[] = 'DATE(o.created_at) >= ?';
    $orderParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $orderWhere[] = 'DATE(o.created_at) <= ?';
    $orderParams[] = $dateTo;
}

// Group orders by shipment
$ordersByShipment = [];
$shipmentTotals = [];
$shipmentStmt = db()->prepare(
    'SELECT s.id, s.shipment_number, s.origin_country_id, s.status, s.shipping_type, s.shipment_date '
    . 'FROM shipments s '
    . 'WHERE s.deleted_at IS NULL'
);
$shipmentStmt->execute();
$shipments = [];
foreach ($shipmentStmt->fetchAll() as $s) {
    $shipments[$s['id']] = $s;
}

$ordersStmt = db()->prepare(
    'SELECT o.id, o.tracking_number, o.total_price, o.created_at, o.shipment_id, '
    . 's.shipment_number, c.name AS customer_name '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'WHERE ' . implode(' AND ', $orderWhere) . ' '
    . 'ORDER BY s.shipment_number, o.created_at DESC, o.id DESC'
);
$ordersStmt->execute($orderParams);
$orders = $ordersStmt->fetchAll();
$orderCount = count($orders);
$orderTotal = 0.0;
foreach ($orders as $order) {
    $sid = $order['shipment_id'] ?? 0;
    if (!isset($ordersByShipment[$sid])) {
        $ordersByShipment[$sid] = [];
        $shipmentTotals[$sid] = 0.0;
    }
    $ordersByShipment[$sid][] = $order;
    $shipmentTotals[$sid] += (float)($order['total_price'] ?? 0);
    $orderTotal += (float)($order['total_price'] ?? 0);
}

$generalWhere = ['e.deleted_at IS NULL'];
$generalParams = [];
if ($dateFrom !== '') {
    $generalWhere[] = 'COALESCE(e.expense_date, DATE(e.created_at)) >= ?';
    $generalParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $generalWhere[] = 'COALESCE(e.expense_date, DATE(e.created_at)) <= ?';
    $generalParams[] = $dateTo;
}

$generalStmt = db()->prepare(
    'SELECT e.id, e.title, e.amount, e.expense_date, e.note, e.created_at, '
    . 'b.name AS branch_name, s.shipment_number '
    . 'FROM general_expenses e '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'LEFT JOIN shipments s ON s.id = e.shipment_id '
    . 'WHERE ' . implode(' AND ', $generalWhere) . ' '
    . 'ORDER BY COALESCE(e.expense_date, DATE(e.created_at)) DESC, e.id DESC'
);
$generalStmt->execute($generalParams);
$generalExpenses = $generalStmt->fetchAll();

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
$staffStmt = db()->prepare(
    'SELECT e.id, e.type, e.amount, e.expense_date, e.salary_month, e.note, e.created_at, '
    . 's.name AS staff_name, b.name AS branch_name '
    . 'FROM staff_expenses e '
    . 'LEFT JOIN staff_members s ON s.id = e.staff_id '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'WHERE ' . implode(' AND ', $staffWhere) . ' '
    . 'ORDER BY COALESCE(e.expense_date, DATE(e.created_at)) DESC, e.id DESC'
);
$staffStmt->execute($staffParams);
$staffExpenses = $staffStmt->fetchAll();

$generalTotal = 0.0;
foreach ($generalExpenses as $row) {
    $generalTotal += (float) ($row['amount'] ?? 0);
}
$staffTotal = 0.0;
foreach ($staffExpenses as $row) {
    $staffTotal += (float) ($row['amount'] ?? 0);
}
$expenseTotal = $generalTotal + $staffTotal;
$netTotal = $orderTotal - $expenseTotal;

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
    <title>Net Report</title>
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
        .net-positive { color: #0a7d33; }
        .net-negative { color: #b21a1a; }
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
            <div><strong>Net Report</strong></div>
            <div>Period: <?= $escape($periodLabel) ?></div>
        </div>
    </header>

    <section class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div>Orders count</div>
                <strong><?= number_format($orderCount) ?></strong>
            </div>
            <div class="summary-card">
                <div>Order income</div>
                <strong><?= number_format($orderTotal, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Total expenses</div>
                <strong><?= number_format($expenseTotal, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Net total</div>
                <strong class="<?= $netTotal >= 0 ? 'net-positive' : 'net-negative' ?>">
                    <?= number_format($netTotal, 2) ?>
                </strong>
            </div>
        </div>
    </section>

    <div class="actions actions-top">
        <form method="get" style="display:inline-block; margin-right:12px;">
            <input type="hidden" name="date_from" value="<?= $escape($dateFrom) ?>">
            <input type="hidden" name="date_to" value="<?= $escape($dateTo) ?>">
            <label>View mode:
                <select name="mode" onchange="this.form.submit()">
                    <option value="detailed"<?= $mode==='detailed'?' selected':'' ?>>Detailed</option>
                    <option value="brief"<?= $mode==='brief'?' selected':'' ?>>Brief (totals only)</option>
                </select>
            </label>
        </form>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <section class="section">
        <h3>Income & Expenses by Month</h3>
        <?php if ($mode === 'brief'): ?>
            <?php
            // Group orders, generalExpenses, staffExpenses by month
            $monthTotals = [];
            foreach ($orders as $order) {
                $month = $order['created_at'] ? date('Y-m', strtotime($order['created_at'])) : '';
                if (!$month) continue;
                if (!isset($monthTotals[$month])) $monthTotals[$month] = ['income'=>0,'orders'=>0,'gen'=>0,'staff'=>0];
                $monthTotals[$month]['income'] += (float)($order['total_price'] ?? 0);
                $monthTotals[$month]['orders']++;
            }
            foreach ($generalExpenses as $row) {
                $dt = $row['expense_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                $month = $dt ? date('Y-m', strtotime($dt)) : '';
                if (!$month) continue;
                if (!isset($monthTotals[$month])) $monthTotals[$month] = ['income'=>0,'orders'=>0,'gen'=>0,'staff'=>0];
                $monthTotals[$month]['gen'] += (float)($row['amount'] ?? 0);
            }
            foreach ($staffExpenses as $row) {
                $dt = $row['expense_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                $month = $dt ? date('Y-m', strtotime($dt)) : '';
                if (!$month) continue;
                if (!isset($monthTotals[$month])) $monthTotals[$month] = ['income'=>0,'orders'=>0,'gen'=>0,'staff'=>0];
                $monthTotals[$month]['staff'] += (float)($row['amount'] ?? 0);
            }
            ksort($monthTotals);
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Income</th>
                        <th>Orders</th>
                        <th>General Expenses</th>
                        <th>Staff Expenses</th>
                        <th>Total Expenses</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthTotals as $month => $tot): ?>
                        <?php $exp = $tot['gen'] + $tot['staff']; ?>
                        <tr>
                            <td><?= $escape($month) ?></td>
                            <td><?= number_format($tot['income'], 2) ?></td>
                            <td><?= number_format($tot['orders']) ?></td>
                            <td><?= number_format($tot['gen'], 2) ?></td>
                            <td><?= number_format($tot['staff'], 2) ?></td>
                            <td><?= number_format($exp, 2) ?></td>
                            <td><?= number_format($tot['income'] - $exp, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php if (empty($ordersByShipment)): ?>
                <p>No shipments with income found for this period.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Shipment</th>
                            <th>Orders count</th>
                            <th>Total income</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordersByShipment as $sid => $ordersList): ?>
                            <?php $shipment = $shipments[$sid] ?? null; ?>
                            <tr>
                                <td><?= $escape($shipment['shipment_number'] ?? '-') ?></td>
                                <td><?= count($ordersList) ?></td>
                                <td><?= number_format($shipmentTotals[$sid] ?? 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>General expenses</h3>
        <?php if (empty($generalExpenses)): ?>
            <p>No general expenses found for this period.</p>
        <?php else: ?>
            <?php if ($mode === 'detailed'): ?>
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
                        <?php foreach ($generalExpenses as $row): ?>
                            <?php
                            $dateLabel = $row['expense_date'] ?: ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : '');
                            ?>
                            <tr>
                                <td><?= $escape($dateLabel ?: '-') ?></td>
                                <td><?= $escape($row['title'] ?? '-') ?></td>
                                <td><?= $escape($row['shipment_number'] ?? '-') ?></td>
                                <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                                <td><?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                                <td><?= $escape($row['note'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <?php
                $byTitle = [];
                foreach ($generalExpenses as $row) {
                    $title = $row['title'] ?? '-';
                    if (!isset($byTitle[$title])) $byTitle[$title] = 0.0;
                    $byTitle[$title] += (float)($row['amount'] ?? 0);
                }
                ?>
                <table>
                    <thead>
                        <tr><th>Category</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byTitle as $cat => $amt): ?>
                            <tr><td><?= $escape($cat) ?></td><td><?= number_format($amt, 2) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Staff expenses</h3>
        <?php if (empty($staffExpenses)): ?>
            <p>No staff expenses found for this period.</p>
        <?php else: ?>
            <?php if ($mode === 'detailed'): ?>
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
                        <?php foreach ($staffExpenses as $row): ?>
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
            <?php else: ?>
                <?php
                $byType = [];
                foreach ($staffExpenses as $row) {
                    $type = $typeLabels[$row['type'] ?? ''] ?? ($row['type'] ?? '-');
                    if (!isset($byType[$type])) $byType[$type] = 0.0;
                    $byType[$type] += (float)($row['amount'] ?? 0);
                }
                ?>
                <table>
                    <thead>
                        <tr><th>Type</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byType as $cat => $amt): ?>
                            <tr><td><?= $escape($cat) ?></td><td><?= number_format($amt, 2) ?></td></tr>
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
