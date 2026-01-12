<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/company.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/services/finance_service.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Sub Branch']);
$input = api_read_input();

$invoiceId = api_int($input['invoice_id'] ?? ($input['id'] ?? null));
if (!$invoiceId) {
    api_error('invoice_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM invoices WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$invoiceId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Invoice not found', 404);
}

$role = $user['role'] ?? '';
if ($role === 'Sub Branch') {
    $userBranchId = api_int($user['branch_id'] ?? null);
    if (!$userBranchId) {
        api_error('Branch scope required', 403);
    }
    if ((int) ($before['branch_id'] ?? 0) !== $userBranchId) {
        api_error('Invoice does not belong to this branch', 403);
    }
}

$updateOrders = array_key_exists('order_ids', $input);
$updateCurrency = array_key_exists('currency', $input);
$updateNote = array_key_exists('note', $input);
$updatePoints = array_key_exists('points_used', $input);

if (!$updateOrders && !$updateCurrency && !$updateNote && !$updatePoints) {
    api_error('No fields to update', 422);
}

if ($updateOrders || $updateCurrency || $updatePoints) {
    if (($before['status'] ?? '') === 'void') {
        api_error('Cannot edit a void invoice', 409);
    }
    $receiptStmt = $db->prepare(
        'SELECT 1 FROM transaction_allocations ta '
        . 'JOIN transactions t ON t.id = ta.transaction_id '
        . 'WHERE ta.invoice_id = ? AND t.deleted_at IS NULL AND t.status = ? LIMIT 1'
    );
    $receiptStmt->execute([$invoiceId, 'active']);
    if ($receiptStmt->fetchColumn()) {
        api_error('Cannot edit an invoice with active receipts', 409);
    }
}

$fields = [];
$params = [];
$customerId = (int) ($before['customer_id'] ?? 0);
$branchId = (int) ($before['branch_id'] ?? 0);
$pointsUsedCurrent = (int) ($before['points_used'] ?? 0);
$pointsDiscountCurrent = (float) ($before['points_discount'] ?? 0);
$pointsUsed = $pointsUsedCurrent;
$pointsDiscount = $pointsDiscountCurrent;
$pointsDelta = 0;
$discountDelta = 0.0;

if ($updatePoints) {
    $rawPointsUsed = $input['points_used'] ?? null;
    if ($rawPointsUsed !== null && $rawPointsUsed !== '' && !preg_match('/^-?\d+$/', (string) $rawPointsUsed)) {
        api_error('points_used must be a whole number', 422);
    }
    $pointsUsed = api_int($rawPointsUsed ?? 0, 0) ?? 0;
    $pointsUsed = max(0, (int) $pointsUsed);

    $pointsSettings = company_points_settings();
    $pointsValue = (float) ($pointsSettings['points_value'] ?? 0);
    if ($pointsUsed > 0 && $pointsValue <= 0) {
        api_error('Points value must be configured before using points', 422);
    }

    $pointsStmt = $db->prepare('SELECT points_balance FROM customers WHERE id = ? AND deleted_at IS NULL');
    $pointsStmt->execute([$customerId]);
    $pointsRow = $pointsStmt->fetch();
    if (!$pointsRow) {
        api_error('Customer not found', 404);
    }
    $availablePoints = (float) ($pointsRow['points_balance'] ?? 0) + $pointsUsedCurrent;
    $maxPoints = (int) floor($availablePoints + 0.0001);
    if ($pointsUsed > $maxPoints) {
        api_error('points_used exceeds available points', 422);
    }

    $pointsDiscount = $pointsUsed > 0 ? round($pointsUsed * $pointsValue, 2) : 0.0;
    $pointsDelta = $pointsUsed - $pointsUsedCurrent;
    $discountDelta = $pointsDiscount - $pointsDiscountCurrent;
}

if (array_key_exists('note', $input)) {
    $fields[] = 'note = ?';
    $params[] = api_string($input['note'] ?? null);
}

if ($updateCurrency) {
    $currency = strtoupper(api_string($input['currency'] ?? 'USD') ?? 'USD');
    if (!in_array($currency, ['USD', 'LBP'], true)) {
        api_error('currency must be USD or LBP', 422);
    }
    $fields[] = 'currency = ?';
    $params[] = $currency;
}

if ($updatePoints && !$updateOrders) {
    $total = (float) ($before['total'] ?? 0);
    if ($total + 0.0001 < $pointsDiscount) {
        api_error('Invoice total cannot be lower than points discount', 422);
    }
    $netTotal = max(0, $total - $pointsDiscount);
    $paidTotal = 0.0;
    $dueTotal = $netTotal;
    $status = invoice_status_from_totals($paidTotal, $netTotal);
    $fields[] = 'points_used = ?';
    $params[] = $pointsUsed;
    $fields[] = 'points_discount = ?';
    $params[] = $pointsDiscount;
    $fields[] = 'paid_total = ?';
    $params[] = $paidTotal;
    $fields[] = 'due_total = ?';
    $params[] = $dueTotal;
    $fields[] = 'status = ?';
    $params[] = $status;
}

if ($updateOrders) {
    $orderIds = $input['order_ids'];
    if (!is_array($orderIds)) {
        api_error('order_ids must be an array', 422);
    }
    $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
    if (empty($orderIds)) {
        api_error('order_ids must contain valid ids', 422);
    }

    $existingStmt = $db->prepare('SELECT order_id FROM invoice_items WHERE invoice_id = ?');
    $existingStmt->execute([$invoiceId]);
    $existingOrderIds = array_map(static fn ($row) => (int) $row['order_id'], $existingStmt->fetchAll());
    $existingSet = array_fill_keys($existingOrderIds, true);

    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $orderSql =
        'SELECT o.id, o.customer_id, o.shipment_id, o.sub_branch_id, o.fulfillment_status, o.tracking_number, '
        . 'o.delivery_type, o.unit_type, o.qty, o.weight_type, o.actual_weight, o.w, o.d, o.h, o.rate, '
        . 'o.base_price, o.adjustments_total, o.total_price, s.shipment_number '
        . 'FROM orders o '
        . 'LEFT JOIN shipments s ON s.id = o.shipment_id '
        . "WHERE o.id IN ($placeholders) AND o.deleted_at IS NULL";
    $orderStmt = $db->prepare($orderSql);
    $orderStmt->execute($orderIds);
    $orders = $orderStmt->fetchAll();

    if (count($orders) !== count($orderIds)) {
        api_error('One or more orders were not found', 404);
    }

    $customerId = (int) ($before['customer_id'] ?? 0);
    foreach ($orders as $order) {
        if ((int) $order['customer_id'] !== $customerId) {
            api_error('All orders must belong to the invoice customer', 422);
        }
        $orderId = (int) $order['id'];
        if (!isset($existingSet[$orderId]) && ($order['fulfillment_status'] ?? '') !== 'received_subbranch') {
            api_error('Orders must be received at sub branch before invoicing', 422);
        }
    }

    $orderBranchId = null;
    foreach ($orders as $order) {
        $currentBranchId = (int) ($order['sub_branch_id'] ?? 0);
        if (!$currentBranchId) {
            api_error('Order must belong to a sub branch', 422);
        }
        if ($orderBranchId === null) {
            $orderBranchId = $currentBranchId;
        } elseif ($orderBranchId !== $currentBranchId) {
            api_error('All orders must belong to the same sub branch', 422);
        }
    }
    if ($orderBranchId !== null && (int) ($before['branch_id'] ?? 0) !== $orderBranchId) {
        api_error('Invoice branch must match the orders sub branch', 422);
    }

    $invoiceCheckSql =
        'SELECT ii.order_id FROM invoice_items ii '
        . 'JOIN invoices i ON i.id = ii.invoice_id '
        . "WHERE ii.order_id IN ($placeholders) AND i.deleted_at IS NULL AND i.status <> 'void' "
        . 'AND i.id <> ?';
    $invoiceCheckStmt = $db->prepare($invoiceCheckSql);
    $invoiceCheckStmt->execute(array_merge($orderIds, [$invoiceId]));
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
    if ($total + 0.0001 < $pointsDiscount) {
        api_error('Invoice total cannot be lower than points discount', 422);
    }

    $netTotal = max(0, $total - $pointsDiscount);
    $paidTotal = 0.0;
    $dueTotal = $netTotal;
    $status = invoice_status_from_totals($paidTotal, $netTotal);

    $fields[] = 'total = ?';
    $params[] = $total;
    $fields[] = 'paid_total = ?';
    $params[] = $paidTotal;
    $fields[] = 'due_total = ?';
    $params[] = $dueTotal;
    $fields[] = 'status = ?';
    $params[] = $status;
    if ($updatePoints) {
        $fields[] = 'points_used = ?';
        $params[] = $pointsUsed;
        $fields[] = 'points_discount = ?';
        $params[] = $pointsDiscount;
    }

    $existingOrderIdMap = array_fill_keys($existingOrderIds, true);
    $newOrderIdMap = array_fill_keys(array_map('intval', $orderIds), true);
    $removedOrders = array_values(array_diff($existingOrderIds, $orderIds));
    $addedOrders = array_values(array_diff($orderIds, $existingOrderIds));

    $deleteItems = $db->prepare('DELETE FROM invoice_items WHERE invoice_id = ?');
    $insertItem = $db->prepare(
        'INSERT INTO invoice_items (invoice_id, order_id, order_snapshot_json, line_total) VALUES (?, ?, ?, ?)'
    );
    $updateOrderStatus = $db->prepare(
        'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );

    $db->beginTransaction();
    try {
        $deleteItems->execute([$invoiceId]);
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

        foreach ($removedOrders as $orderId) {
            $updateOrderStatus->execute(['received_subbranch', $user['id'] ?? null, $orderId]);
        }
        foreach ($addedOrders as $orderId) {
            $order = null;
            foreach ($orders as $row) {
                if ((int) $row['id'] === (int) $orderId) {
                    $order = $row;
                    break;
                }
            }
            if (!$order) {
                continue;
            }
            $deliveryType = $order['delivery_type'] ?? 'pickup';
            $nextStatus = $deliveryType === 'delivery' ? 'with_delivery' : 'picked_up';
            $updateOrderStatus->execute([$nextStatus, $user['id'] ?? null, $orderId]);
        }

        $fields[] = 'updated_at = NOW()';
        $fields[] = 'updated_by_user_id = ?';
        $params[] = $user['id'] ?? null;
        $params[] = $invoiceId;

        $sql = 'UPDATE invoices SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($updatePoints) {
            if ($pointsDelta !== 0) {
                adjust_customer_points($db, $customerId, -$pointsDelta);
            }
            if (abs($discountDelta) > 0.0001) {
                adjust_customer_balance($db, $customerId, -$discountDelta);
                record_customer_balance(
                    $db,
                    $customerId,
                    $branchId,
                    -$discountDelta,
                    'adjustment',
                    'invoice',
                    $invoiceId,
                    $user['id'] ?? null,
                    'Points discount adjusted'
                );
                record_branch_balance(
                    $db,
                    $branchId,
                    -$discountDelta,
                    'adjustment',
                    'invoice',
                    $invoiceId,
                    $user['id'] ?? null,
                    'Points discount adjusted'
                );
            }
        }

        $afterStmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
        $afterStmt->execute([$invoiceId]);
        $after = $afterStmt->fetch();
        audit_log($user, 'invoices.update', 'invoice', $invoiceId, $before, $after, [
            'order_ids' => $orderIds,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        api_error('Failed to update invoice', 500);
    }

    api_json(['ok' => true]);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;

$params[] = $invoiceId;

$sql = 'UPDATE invoices SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
try {
    $db->beginTransaction();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($updatePoints) {
        if ($pointsDelta !== 0) {
            adjust_customer_points($db, $customerId, -$pointsDelta);
        }
        if (abs($discountDelta) > 0.0001) {
            adjust_customer_balance($db, $customerId, -$discountDelta);
            record_customer_balance(
                $db,
                $customerId,
                $branchId,
                -$discountDelta,
                'adjustment',
                'invoice',
                $invoiceId,
                $user['id'] ?? null,
                'Points discount adjusted'
            );
            record_branch_balance(
                $db,
                $branchId,
                -$discountDelta,
                'adjustment',
                'invoice',
                $invoiceId,
                $user['id'] ?? null,
                'Points discount adjusted'
            );
        }
    }
    $afterStmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $afterStmt->execute([$invoiceId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'invoices.update', 'invoice', $invoiceId, $before, $after);
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to update invoice', 500);
}

api_json(['ok' => true]);
