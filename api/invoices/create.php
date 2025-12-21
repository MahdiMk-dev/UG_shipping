<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
$input = api_read_input();

$customerId = api_int($input['customer_id'] ?? null);
$branchId = api_int($input['branch_id'] ?? null);
$orderIds = $input['order_ids'] ?? null;
$invoiceNo = api_string($input['invoice_no'] ?? null);
$note = api_string($input['note'] ?? null);
$issuedAt = api_string($input['issued_at'] ?? null);

if (!$customerId || !$branchId || !is_array($orderIds) || empty($orderIds)) {
    api_error('customer_id, branch_id, and order_ids are required', 422);
}

$orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
if (empty($orderIds)) {
    api_error('order_ids must contain valid ids', 422);
}

$db = db();

$customerStmt = $db->prepare('SELECT id, is_system FROM customers WHERE id = ? AND deleted_at IS NULL');
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();
if (!$customer) {
    api_error('Customer not found', 404);
}
if ((int) $customer['is_system'] === 1) {
    api_error('Invoices are not allowed for system customers', 422);
}

$branchStmt = $db->prepare('SELECT id FROM branches WHERE id = ? AND deleted_at IS NULL');
$branchStmt->execute([$branchId]);
if (!$branchStmt->fetch()) {
    api_error('Branch not found', 404);
}

$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$orderSql =
    'SELECT o.id, o.customer_id, o.shipment_id, o.tracking_number, o.delivery_type, o.unit_type, '
    . 'o.qty, o.weight_type, o.actual_weight, o.w, o.d, o.h, o.rate, o.base_price, '
    . 'o.adjustments_total, o.total_price, s.shipment_number '
    . 'FROM orders o '
    . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
    . "WHERE o.id IN ($placeholders) AND o.deleted_at IS NULL";

$orderStmt = $db->prepare($orderSql);
$orderStmt->execute($orderIds);
$orders = $orderStmt->fetchAll();

if (count($orders) !== count($orderIds)) {
    api_error('One or more orders were not found', 404);
}

foreach ($orders as $order) {
    if ((int) $order['customer_id'] !== $customerId) {
        api_error('All orders must belong to the same customer', 422);
    }
}

$invoiceCheckSql =
    'SELECT ii.order_id FROM invoice_items ii '
    . 'JOIN invoices i ON i.id = ii.invoice_id '
    . "WHERE ii.order_id IN ($placeholders) AND i.deleted_at IS NULL AND i.status <> 'void'";

$invoiceCheckStmt = $db->prepare($invoiceCheckSql);
$invoiceCheckStmt->execute($orderIds);
$alreadyInvoiced = $invoiceCheckStmt->fetchAll();

if (!empty($alreadyInvoiced)) {
    $conflicts = array_map(static fn ($row) => (int) $row['order_id'], $alreadyInvoiced);
    api_error('Some orders are already invoiced', 409, ['order_ids' => $conflicts]);
}

$adjStmt = $db->prepare(
    "SELECT order_id, title, description, kind, calc_type, value, computed_amount "
    . "FROM order_adjustments WHERE order_id IN ($placeholders) AND deleted_at IS NULL"
);
$adjStmt->execute($orderIds);
$adjustments = $adjStmt->fetchAll();

$adjustmentsByOrder = [];
foreach ($adjustments as $adj) {
    $orderId = (int) $adj['order_id'];
    if (!isset($adjustmentsByOrder[$orderId])) {
        $adjustmentsByOrder[$orderId] = [];
    }
    $adjustmentsByOrder[$orderId][] = [
        'title' => $adj['title'],
        'description' => $adj['description'],
        'kind' => $adj['kind'],
        'calc_type' => $adj['calc_type'],
        'value' => (float) $adj['value'],
        'computed_amount' => (float) $adj['computed_amount'],
    ];
}

$total = 0.0;
foreach ($orders as $order) {
    $total += (float) $order['total_price'];
}
$total = round($total, 2);

$issuedAtValue = $issuedAt ?: date('Y-m-d H:i:s');

$insertInvoice = $db->prepare(
    'INSERT INTO invoices '
    . '(customer_id, branch_id, invoice_no, status, total, paid_total, due_total, issued_at, issued_by_user_id, note) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insertItem = $db->prepare(
    'INSERT INTO invoice_items (invoice_id, order_id, order_snapshot_json, line_total) '
    . 'VALUES (?, ?, ?, ?)'
);

$db->beginTransaction();

try {
    $finalInvoiceNo = $invoiceNo;
    $tries = 0;
    $inserted = false;

    while (!$inserted && $tries < 3) {
        $tries++;
        if (!$finalInvoiceNo) {
            $finalInvoiceNo = 'INV-' . date('Ymd-His') . '-' . random_int(100, 999);
        }

        try {
            $insertInvoice->execute([
                $customerId,
                $branchId,
                $finalInvoiceNo,
                'open',
                $total,
                0,
                $total,
                $issuedAtValue,
                $user['id'] ?? null,
                $note,
            ]);
            $inserted = true;
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                if ($invoiceNo) {
                    api_error('Invoice number already exists', 409);
                }
                $finalInvoiceNo = null;
                continue;
            }
            throw $e;
        }
    }

    if (!$inserted) {
        api_error('Unable to generate invoice number', 500);
    }

    $invoiceId = (int) $db->lastInsertId();

    foreach ($orders as $order) {
        $orderId = (int) $order['id'];
        $snapshot = [
            'order_id' => $orderId,
            'tracking_number' => $order['tracking_number'],
            'shipment_id' => (int) $order['shipment_id'],
            'shipment_number' => $order['shipment_number'],
            'delivery_type' => $order['delivery_type'],
            'unit_type' => $order['unit_type'],
            'qty' => (float) $order['qty'],
            'weight_type' => $order['weight_type'],
            'actual_weight' => $order['actual_weight'] !== null ? (float) $order['actual_weight'] : null,
            'w' => $order['w'] !== null ? (float) $order['w'] : null,
            'd' => $order['d'] !== null ? (float) $order['d'] : null,
            'h' => $order['h'] !== null ? (float) $order['h'] : null,
            'rate' => (float) $order['rate'],
            'base_price' => (float) $order['base_price'],
            'adjustments_total' => (float) $order['adjustments_total'],
            'total_price' => (float) $order['total_price'],
            'adjustments' => $adjustmentsByOrder[$orderId] ?? [],
        ];

        $insertItem->execute([
            $invoiceId,
            $orderId,
            json_encode($snapshot),
            $order['total_price'],
        ]);
    }

    $db->prepare('UPDATE customers SET balance = balance - ? WHERE id = ?')
        ->execute([$total, $customerId]);

    $invRowStmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $invRowStmt->execute([$invoiceId]);
    $after = $invRowStmt->fetch();
    audit_log($user, 'invoices.create', 'invoice', $invoiceId, null, $after, [
        'order_ids' => $orderIds,
        'total' => $total,
    ]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    api_error('Failed to create invoice', 500);
}

api_json([
    'ok' => true,
    'id' => $invoiceId,
    'invoice_no' => $finalInvoiceNo,
    'total' => $total,
]);
