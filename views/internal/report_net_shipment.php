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

$orderWhere = ['o.deleted_at IS NULL', 's.deleted_at IS NULL'];
$orderParams = [];
if ($shipmentId > 0) {
    $orderWhere[] = 'o.shipment_id = ?';
    $orderParams[] = $shipmentId;
}
if ($dateFrom !== '') {
    $orderWhere[] = 'DATE(o.created_at) >= ?';
    $orderParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $orderWhere[] = 'DATE(o.created_at) <= ?';
    $orderParams[] = $dateTo;
}

$ordersStmt = db()->prepare(
    'SELECT o.id, o.tracking_number, o.total_price, o.created_at, o.shipment_id, '
    . 's.shipment_number, c.name AS customer_name, '
    . 'i.invoice_no, i.status AS invoice_status '
    . 'FROM orders o '
    . 'INNER JOIN shipments s ON s.id = o.shipment_id '
    . 'LEFT JOIN customers c ON c.id = o.customer_id '
    . 'LEFT JOIN (SELECT ii.order_id, MAX(ii.invoice_id) AS invoice_id '
        . 'FROM invoice_items ii GROUP BY ii.order_id) iix ON iix.order_id = o.id '
    . 'LEFT JOIN invoices i ON i.id = iix.invoice_id AND i.deleted_at IS NULL '
    . 'WHERE ' . implode(' AND ', $orderWhere) . ' '
    . 'ORDER BY s.shipment_number, o.created_at DESC, o.id DESC'
);
$ordersStmt->execute($orderParams);
$orders = $ordersStmt->fetchAll();

$ordersByShipment = [];
$shipmentTotals = [];
$orderTotal = 0.0;
foreach ($orders as $order) {
    $sid = (int) ($order['shipment_id'] ?? 0);
    if (!isset($ordersByShipment[$sid])) {
        $ordersByShipment[$sid] = [];
        $shipmentTotals[$sid] = 0.0;
    }
    $ordersByShipment[$sid][] = $order;
    $shipmentTotals[$sid] += (float) ($order['total_price'] ?? 0);
    $orderTotal += (float) ($order['total_price'] ?? 0);
}

$expenseWhere = ['e.deleted_at IS NULL', 'e.shipment_id IS NOT NULL', 's.deleted_at IS NULL'];
$expenseParams = [];
if ($shipmentId > 0) {
    $expenseWhere[] = 'e.shipment_id = ?';
    $expenseParams[] = $shipmentId;
}
if ($dateFrom !== '') {
    $expenseWhere[] = 'COALESCE(e.expense_date, DATE(e.created_at)) >= ?';
    $expenseParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $expenseWhere[] = 'COALESCE(e.expense_date, DATE(e.created_at)) <= ?';
    $expenseParams[] = $dateTo;
}

$expenseStmt = db()->prepare(
    'SELECT e.shipment_id, SUM(e.amount) AS total '
    . 'FROM general_expenses e '
    . 'INNER JOIN shipments s ON s.id = e.shipment_id '
    . 'WHERE ' . implode(' AND ', $expenseWhere) . ' '
    . 'GROUP BY e.shipment_id'
);
$expenseStmt->execute($expenseParams);
$expenseTotals = [];
$expenseTotal = 0.0;
foreach ($expenseStmt->fetchAll() as $row) {
    $sid = (int) ($row['shipment_id'] ?? 0);
    $amount = (float) ($row['total'] ?? 0);
    $expenseTotals[$sid] = $amount;
    $expenseTotal += $amount;
}

$shipmentStmt = db()->prepare(
    'SELECT s.id, s.shipment_number '
    . 'FROM shipments s '
    . 'WHERE s.deleted_at IS NULL' . ($shipmentId > 0 ? ' AND s.id = ?' : '')
);
if ($shipmentId > 0) {
    $shipmentStmt->execute([$shipmentId]);
} else {
    $shipmentStmt->execute();
}
$shipments = [];
foreach ($shipmentStmt->fetchAll() as $row) {
    $shipments[(int) $row['id']] = $row;
}

$paidOrders = [];
$unpaidOrders = [];
foreach ($orders as $order) {
    $status = strtolower((string) ($order['invoice_status'] ?? ''));
    if ($status === 'paid') {
        $paidOrders[] = $order;
    } else {
        $unpaidOrders[] = $order;
    }
}

$netTotal = $orderTotal - $expenseTotal;

$company = company_settings();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$periodLabel = $dateFrom && $dateTo ? "{$dateFrom} to {$dateTo}" : 'All dates';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Net Report by Shipment</title>
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
            <div><strong>Net Report by Shipment</strong></div>
            <div>Period: <?= $escape($periodLabel) ?></div>
        </div>
    </header>

    <section class="section">
        <div class="summary-grid">
            <div class="summary-card">
                <div>Shipments</div>
                <strong><?= number_format(count($ordersByShipment)) ?></strong>
            </div>
            <div class="summary-card">
                <div>Order income</div>
                <strong><?= number_format($orderTotal, 2) ?></strong>
            </div>
            <div class="summary-card">
                <div>Shipment expenses</div>
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
        <h3>Net by shipment</h3>
        <?php if (empty($ordersByShipment) && empty($expenseTotals)): ?>
            <p>No shipment data found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Shipment</th>
                        <th>Orders count</th>
                        <th>Order income</th>
                        <th>Shipment expenses</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $shipmentIds = array_unique(array_merge(array_keys($ordersByShipment), array_keys($expenseTotals)));
                    sort($shipmentIds);
                    foreach ($shipmentIds as $sid):
                        $shipment = $shipments[$sid] ?? null;
                        $income = $shipmentTotals[$sid] ?? 0;
                        $expense = $expenseTotals[$sid] ?? 0;
                        $net = $income - $expense;
                    ?>
                        <tr>
                            <td><?= $escape($shipment['shipment_number'] ?? '-') ?></td>
                            <td><?= number_format(count($ordersByShipment[$sid] ?? [])) ?></td>
                            <td><?= number_format($income, 2) ?></td>
                            <td><?= number_format($expense, 2) ?></td>
                            <td class="<?= $net >= 0 ? 'net-positive' : 'net-negative' ?>">
                                <?= number_format($net, 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Paid orders (fully paid invoices)</h3>
        <?php if (empty($paidOrders)): ?>
            <p>No fully paid orders found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Shipment</th>
                        <th>Tracking</th>
                        <th>Customer</th>
                        <th>Invoice</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paidOrders as $order): ?>
                        <tr>
                            <td><?= $escape($order['shipment_number'] ?? '-') ?></td>
                            <td><?= $escape($order['tracking_number'] ?? '-') ?></td>
                            <td><?= $escape($order['customer_name'] ?? '-') ?></td>
                            <td><?= $escape($order['invoice_no'] ?? '-') ?></td>
                            <td><?= number_format((float) ($order['total_price'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Unpaid orders (not fully paid)</h3>
        <?php if (empty($unpaidOrders)): ?>
            <p>No unpaid orders found for this period.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Shipment</th>
                        <th>Tracking</th>
                        <th>Customer</th>
                        <th>Invoice</th>
                        <th>Status</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unpaidOrders as $order): ?>
                        <tr>
                            <td><?= $escape($order['shipment_number'] ?? '-') ?></td>
                            <td><?= $escape($order['tracking_number'] ?? '-') ?></td>
                            <td><?= $escape($order['customer_name'] ?? '-') ?></td>
                            <td><?= $escape($order['invoice_no'] ?? '-') ?></td>
                            <td><?= $escape($order['invoice_status'] ?? 'unbilled') ?></td>
                            <td><?= number_format((float) ($order['total_price'] ?? 0), 2) ?></td>
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
