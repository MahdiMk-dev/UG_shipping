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

$orderTotalsStmt = db()->prepare(
    'SELECT COUNT(*) AS order_count, COALESCE(SUM(o.total_price), 0) AS order_total '
    . 'FROM orders o '
    . 'WHERE ' . implode(' AND ', $orderWhere)
);
$orderTotalsStmt->execute($orderParams);
$orderTotals = $orderTotalsStmt->fetch();
$orderCount = (int) ($orderTotals['order_count'] ?? 0);
$orderTotal = (float) ($orderTotals['order_total'] ?? 0);

$ordersStmt = db()->prepare(
    'SELECT o.id, o.tracking_number, o.total_price, o.created_at, '
    . 's.shipment_number, c.name AS customer_name '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'WHERE ' . implode(' AND ', $orderWhere) . ' '
    . 'ORDER BY o.created_at DESC, o.id DESC'
);
$ordersStmt->execute($orderParams);
$orders = $ordersStmt->fetchAll();

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
$staffStmt = db()->prepare(
    'SELECT e.id, e.type, e.amount, e.expense_date, e.note, e.created_at, '
    . 's.name AS staff_name, b.name AS branch_name '
    . 'FROM staff_expenses e '
    . 'LEFT JOIN staff_members s ON s.id = e.staff_id '
    . 'LEFT JOIN branches b ON b.id = e.branch_id '
    . 'WHERE ' . implode(' AND ', $staffWhere) . ' '
    . 'ORDER BY COALESCE(e.expense_date, DATE(e.created_at)) DESC, e.id DESC'
);
$staffStmt->execute($staffParams);
$staffExpenses = $staffStmt->fetchAll();

$salaryMonths = [];
$monthStart = new DateTime($dateFrom);
$monthStart->modify('first day of this month');
$monthEnd = new DateTime($dateTo);
$monthEnd->modify('first day of this month');
$cursor = clone $monthStart;
while ($cursor <= $monthEnd) {
    $salaryMonths[] = $cursor->format('Y-m-01');
    $cursor->modify('+1 month');
}

$staffListStmt = db()->prepare(
    'SELECT s.id, s.name, s.base_salary, s.status, s.hired_at, b.name AS branch_name '
    . 'FROM staff_members s '
    . 'LEFT JOIN branches b ON b.id = s.branch_id '
    . 'WHERE s.deleted_at IS NULL'
);
$staffListStmt->execute();
$staffList = $staffListStmt->fetchAll();

$advanceMap = [];
if (!empty($salaryMonths)) {
    $firstMonth = new DateTime($salaryMonths[0]);
    $lastMonth = new DateTime($salaryMonths[count($salaryMonths) - 1]);
    $advanceStart = $firstMonth->modify('-1 month')->format('Y-m-01');
    $advanceEnd = $lastMonth->modify('-1 month')->format('Y-m-t');

    $advanceStmt = db()->prepare(
        'SELECT staff_id, COALESCE(expense_date, DATE(created_at)) AS expense_day, amount '
        . 'FROM staff_expenses '
        . "WHERE deleted_at IS NULL AND type = 'advance' "
        . 'AND COALESCE(expense_date, DATE(created_at)) BETWEEN ? AND ?'
    );
    $advanceStmt->execute([$advanceStart, $advanceEnd]);
    $advanceRows = $advanceStmt->fetchAll();

    foreach ($advanceRows as $advance) {
        $staffId = (int) $advance['staff_id'];
        $expenseDay = $advance['expense_day'] ?: null;
        if (!$expenseDay) {
            continue;
        }
        $expenseMonth = new DateTime($expenseDay);
        $expenseMonth->modify('first day of this month');
        $salaryMonthKey = (clone $expenseMonth)->modify('first day of next month')->format('Y-m-01');
        if (!isset($advanceMap[$staffId])) {
            $advanceMap[$staffId] = [];
        }
        if (!isset($advanceMap[$staffId][$salaryMonthKey])) {
            $advanceMap[$staffId][$salaryMonthKey] = 0.0;
        }
        $advanceMap[$staffId][$salaryMonthKey] += (float) ($advance['amount'] ?? 0);
    }
}

$salaryRows = [];
$salaryTotal = 0.0;
$rangeStart = strtotime($dateFrom);
$rangeEnd = strtotime($dateTo);
foreach ($salaryMonths as $salaryDate) {
    $salaryTs = strtotime($salaryDate);
    if ($salaryTs < $rangeStart || $salaryTs > $rangeEnd) {
        continue;
    }
    foreach ($staffList as $staff) {
        if (($staff['status'] ?? '') !== 'active') {
            continue;
        }
        $hiredAt = $staff['hired_at'] ?? null;
        if ($hiredAt && strtotime($hiredAt) > strtotime($salaryDate)) {
            continue;
        }
        $baseSalary = (float) ($staff['base_salary'] ?? 0);
        if ($baseSalary <= 0) {
            continue;
        }
        $advancePrev = $advanceMap[(int) $staff['id']][$salaryDate] ?? 0.0;
        $advanceDeducted = min($advancePrev, $baseSalary);
        $netSalary = max(0.0, $baseSalary - $advancePrev);
        $salaryRows[] = [
            'salary_date' => $salaryDate,
            'staff_name' => $staff['name'] ?? '-',
            'branch_name' => $staff['branch_name'] ?? '-',
            'base_salary' => $baseSalary,
            'advance_deducted' => $advanceDeducted,
            'net_salary' => $netSalary,
        ];
        $salaryTotal += $netSalary;
    }
}

$generalTotal = 0.0;
foreach ($generalExpenses as $row) {
    $generalTotal += (float) ($row['amount'] ?? 0);
}
$staffTotal = 0.0;
foreach ($staffExpenses as $row) {
    $staffTotal += (float) ($row['amount'] ?? 0);
}
$expenseTotal = $generalTotal + $staffTotal + $salaryTotal;
$netTotal = $orderTotal - $expenseTotal;

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$periodLabel = $dateFrom && $dateTo ? "{$dateFrom} to {$dateTo}" : 'All dates';
$typeLabels = [
    'salary_adjustment' => 'Salary adjustment',
    'advance' => 'Advance',
    'bonus' => 'Bonus',
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

    <section class="section">
        <h3>Orders (income)</h3>
        <?php if (empty($orders)): ?>
            <p>No orders found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shipment</th>
                        <th>Tracking</th>
                        <th>Customer</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php $dateLabel = $order['created_at'] ? date('Y-m-d', strtotime($order['created_at'])) : ''; ?>
                        <tr>
                            <td><?= $escape($dateLabel ?: '-') ?></td>
                            <td><?= $escape($order['shipment_number'] ?? '-') ?></td>
                            <td><?= $escape($order['tracking_number'] ?? '-') ?></td>
                            <td><?= $escape($order['customer_name'] ?? '-') ?></td>
                            <td><?= number_format((float) ($order['total_price'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>General expenses</h3>
        <?php if (empty($generalExpenses)): ?>
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
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Staff expenses</h3>
        <?php if (empty($staffExpenses)): ?>
            <p>No staff expenses found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff</th>
                        <th>Type</th>
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
                        ?>
                        <tr>
                            <td><?= $escape($dateLabel ?: '-') ?></td>
                            <td><?= $escape($row['staff_name'] ?? '-') ?></td>
                            <td><?= $escape($typeLabel) ?></td>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                            <td><?= $escape($row['note'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Salaries (monthly payouts)</h3>
        <?php if (empty($salaryRows)): ?>
            <p>No salary payouts found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Salary date</th>
                        <th>Staff</th>
                        <th>Branch</th>
                        <th>Base salary</th>
                        <th>Advance deducted</th>
                        <th>Net salary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salaryRows as $row): ?>
                        <tr>
                            <td><?= $escape($row['salary_date']) ?></td>
                            <td><?= $escape($row['staff_name'] ?? '-') ?></td>
                            <td><?= $escape($row['branch_name'] ?? '-') ?></td>
                            <td><?= number_format((float) ($row['base_salary'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['advance_deducted'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($row['net_salary'] ?? 0), 2) ?></td>
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
