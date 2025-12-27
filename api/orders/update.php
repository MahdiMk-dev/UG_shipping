<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/services/invoice_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);
$input = api_read_input();

$orderId = api_int($input['order_id'] ?? ($input['id'] ?? null));
if (!$orderId) {
    api_error('order_id is required', 422);
}

$db = db();
$stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    api_error('Order not found', 404);
}

$orderInvoiced = order_has_active_invoice($db, $orderId);
$blockedKeys = [
    'shipment_id',
    'customer_id',
    'collection_id',
    'tracking_number',
    'delivery_type',
    'unit_type',
    'weight_type',
    'actual_weight',
    'w',
    'd',
    'h',
    'rate',
    'adjustments',
];
foreach ($blockedKeys as $blockedKey) {
    if ($orderInvoiced && array_key_exists($blockedKey, $input)) {
        api_error('Order is already invoiced. Price changes are locked.', 409);
    }
}
$shipmentStatusStmt = $db->prepare('SELECT status, origin_country_id FROM shipments WHERE id = ? AND deleted_at IS NULL');
$shipmentStatusStmt->execute([$order['shipment_id']]);
$shipment = $shipmentStatusStmt->fetch();
if (!$shipment) {
    api_error('Shipment not found', 404);
}
$role = $user['role'] ?? '';
$warehouseCountryId = null;
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if ((int) ($shipment['origin_country_id'] ?? 0) !== (int) $warehouseCountryId) {
        api_error('Forbidden', 403);
    }
}
if ($role === 'Warehouse' && ($shipment['status'] ?? '') !== 'active') {
    api_error('Shipment must be active to edit orders', 403);
}

$previousShipmentId = (int) $order['shipment_id'];
$previousCustomerId = (int) $order['customer_id'];
$previousBranchId = (int) $order['sub_branch_id'];
$previousTotal = (float) $order['total_price'];
$previousStatus = (string) $order['fulfillment_status'];
$fields = [];
$params = [];

$allowedDelivery = ['pickup', 'delivery'];
$allowedUnit = ['kg', 'cbm'];
$allowedWeight = ['actual', 'volumetric'];
$allowedFulfillment = [
    'in_shipment',
    'main_branch',
    'pending_receipt',
    'received_subbranch',
    'closed',
    'returned',
    'canceled',
];
$allowedNotification = ['pending', 'notified'];

$mapFields = [
    'shipment_id' => 'shipment_id',
    'customer_id' => 'customer_id',
    'collection_id' => 'collection_id',
    'tracking_number' => 'tracking_number',
    'delivery_type' => 'delivery_type',
    'unit_type' => 'unit_type',
    'weight_type' => 'weight_type',
    'actual_weight' => 'actual_weight',
    'w' => 'w',
    'd' => 'd',
    'h' => 'h',
    'rate' => 'rate',
    'note' => 'note',
    'fulfillment_status' => 'fulfillment_status',
    'notification_status' => 'notification_status',
];

$newValues = $order;

foreach ($mapFields as $inputKey => $column) {
    if (!array_key_exists($inputKey, $input)) {
        continue;
    }

    $value = $input[$inputKey];

    if (in_array($inputKey, ['shipment_id', 'customer_id', 'collection_id'], true)) {
        $value = api_int($value);
        if ($inputKey !== 'collection_id' && !$value) {
            api_error($inputKey . ' is invalid', 422);
        }
    } elseif (in_array($inputKey, ['delivery_type'], true)) {
        $value = api_string($value);
        if (!$value || !in_array($value, $allowedDelivery, true)) {
            api_error('Invalid delivery_type', 422);
        }
    } elseif (in_array($inputKey, ['unit_type'], true)) {
        $value = api_string($value);
        if (!$value || !in_array($value, $allowedUnit, true)) {
            api_error('Invalid unit_type', 422);
        }
    } elseif (in_array($inputKey, ['weight_type'], true)) {
        $value = api_string($value);
        if (!$value || !in_array($value, $allowedWeight, true)) {
            api_error('Invalid weight_type', 422);
        }
    } elseif (in_array($inputKey, ['rate', 'actual_weight', 'w', 'd', 'h'], true)) {
        $value = api_float($value);
    } elseif ($inputKey === 'note') {
        $value = api_string($value);
    } elseif ($inputKey === 'tracking_number') {
        $value = api_string($value);
        if (!$value) {
            api_error('tracking_number cannot be empty', 422);
        }
    } elseif ($inputKey === 'fulfillment_status') {
        $value = api_string($value);
        if (!$value || !in_array($value, $allowedFulfillment, true)) {
            api_error('Invalid fulfillment_status', 422);
        }
    } elseif ($inputKey === 'notification_status') {
        $value = api_string($value);
        if (!$value || !in_array($value, $allowedNotification, true)) {
            api_error('Invalid notification_status', 422);
        }
    }

    $fields[] = $column . ' = ?';
    $params[] = $value;
    $newValues[$column] = $value;
}

$shipmentIdUpdated = array_key_exists('shipment_id', $input);
$targetShipment = $shipment;
if ($shipmentIdUpdated && (int) $newValues['shipment_id'] !== (int) $order['shipment_id']) {
    $targetStmt = $db->prepare('SELECT status, origin_country_id FROM shipments WHERE id = ? AND deleted_at IS NULL');
    $targetStmt->execute([(int) $newValues['shipment_id']]);
    $targetShipment = $targetStmt->fetch();
    if (!$targetShipment) {
        api_error('Shipment not found', 404);
    }
    if ($role === 'Warehouse') {
        if ((int) ($targetShipment['origin_country_id'] ?? 0) !== (int) $warehouseCountryId) {
            api_error('Forbidden', 403);
        }
        if (($targetShipment['status'] ?? '') !== 'active') {
            api_error('Shipment must be active to edit orders', 403);
        }
    }
}

$customerIdUpdated = array_key_exists('customer_id', $input);
if ($customerIdUpdated || $shipmentIdUpdated) {
    $effectiveCustomerId = $customerIdUpdated ? $newValues['customer_id'] : $order['customer_id'];
    $customerStmt = $db->prepare(
        'SELECT id, sub_branch_id, profile_country_id FROM customers WHERE id = ? AND deleted_at IS NULL'
    );
    $customerStmt->execute([$effectiveCustomerId]);
    $customer = $customerStmt->fetch();
    if (!$customer || empty($customer['sub_branch_id'])) {
        api_error('Customer has no sub branch assigned', 422);
    }
    if (empty($customer['profile_country_id'])) {
        api_error('Customer profile has no country assigned', 422);
    }
    if ((int) $customer['profile_country_id'] !== (int) ($targetShipment['origin_country_id'] ?? 0)) {
        api_error('Customer profile country must match the shipment origin', 422);
    }
    if ($customerIdUpdated) {
        $fields[] = 'sub_branch_id = ?';
        $params[] = $customer['sub_branch_id'];
        $newValues['sub_branch_id'] = $customer['sub_branch_id'];
    }
}

$adjustmentsInputProvided = array_key_exists('adjustments', $input);
$adjustments = null;

if ($adjustmentsInputProvided) {
    if (!is_array($input['adjustments'])) {
        api_error('adjustments must be an array', 422);
    }
    $adjustments = $input['adjustments'];
} else {
    $adjStmt = $db->prepare(
        'SELECT id, title, description, kind, calc_type, value '
        . 'FROM order_adjustments WHERE order_id = ? AND deleted_at IS NULL'
    );
    $adjStmt->execute([$orderId]);
    $adjustments = $adjStmt->fetchAll() ?: [];
}

$normalizedAdjustments = [];
foreach ($adjustments as $adjustment) {
    if (!is_array($adjustment)) {
        api_error('Invalid adjustment payload', 422);
    }
    $title = api_string($adjustment['title'] ?? null);
    if (!$title) {
        api_error('Adjustment title is required', 422);
    }
    $kind = api_string($adjustment['kind'] ?? 'cost') ?? 'cost';
    $calcType = api_string($adjustment['calc_type'] ?? 'amount') ?? 'amount';
    $value = api_float($adjustment['value'] ?? null) ?? 0.0;

    if (!in_array($kind, ['cost', 'discount'], true)) {
        api_error('Invalid adjustment kind', 422);
    }
    if (!in_array($calcType, ['amount', 'percentage'], true)) {
        api_error('Invalid adjustment calc_type', 422);
    }

    $normalizedAdjustments[] = [
        'id' => $adjustment['id'] ?? null,
        'title' => $title,
        'description' => api_string($adjustment['description'] ?? null),
        'kind' => $kind,
        'calc_type' => $calcType,
        'value' => $value,
    ];
}

$weightType = $newValues['weight_type'];
$actualWeight = $newValues['actual_weight'];
$w = $newValues['w'];
$d = $newValues['d'];
$h = $newValues['h'];

if ($weightType === 'actual' && ($actualWeight === null || $actualWeight === '')) {
    api_error('actual_weight is required for actual weight_type', 422);
}
if ($weightType === 'volumetric' && ($w === null || $d === null || $h === null)) {
    api_error('w, d, h are required for volumetric weight_type', 422);
}

$qty = compute_qty($newValues['unit_type'], $weightType, (float) $actualWeight, (float) $w, (float) $d, (float) $h);
$basePrice = compute_base_price($qty, (float) $newValues['rate']);

$computedAdjustments = [];
foreach ($normalizedAdjustments as $adjustment) {
    $amount = $adjustment['calc_type'] === 'percentage'
        ? ($basePrice * ($adjustment['value'] / 100))
        : $adjustment['value'];

    if ($adjustment['kind'] === 'discount') {
        $amount *= -1;
    }

    $adjustment['computed_amount'] = round($amount, 2);
    $computedAdjustments[] = $adjustment;
}

$adjustmentsTotal = compute_adjustments_total($computedAdjustments, $basePrice);
$totalPrice = round($basePrice + $adjustmentsTotal, 2);

$fields[] = 'qty = ?';
$params[] = $qty;
$fields[] = 'base_price = ?';
$params[] = $basePrice;
$fields[] = 'adjustments_total = ?';
$params[] = $adjustmentsTotal;
$fields[] = 'total_price = ?';
$params[] = $totalPrice;
$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;

$params[] = $orderId;

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$db->beginTransaction();

try {
    $sql = 'UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
    $updateStmt = $db->prepare($sql);
    $updateStmt->execute($params);

    if ($adjustmentsInputProvided) {
        $db->prepare('UPDATE order_adjustments SET deleted_at = NOW() WHERE order_id = ? AND deleted_at IS NULL')
            ->execute([$orderId]);

        if (!empty($computedAdjustments)) {
            $adjInsert = $db->prepare(
                'INSERT INTO order_adjustments '
                . '(order_id, title, description, kind, calc_type, value, computed_amount, created_by_user_id) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($computedAdjustments as $adjustment) {
                $adjInsert->execute([
                    $orderId,
                    $adjustment['title'],
                    $adjustment['description'],
                    $adjustment['kind'],
                    $adjustment['calc_type'],
                    $adjustment['value'],
                    $adjustment['computed_amount'],
                    $user['id'] ?? null,
                ]);
            }
        }
    } else {
        if (!empty($computedAdjustments)) {
            $adjUpdate = $db->prepare('UPDATE order_adjustments SET computed_amount = ? WHERE id = ?');
            foreach ($computedAdjustments as $adjustment) {
                if (!$adjustment['id']) {
                    continue;
                }
                $adjUpdate->execute([$adjustment['computed_amount'], $adjustment['id']]);
            }
        }
    }

    $afterStmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $afterStmt->execute([$orderId]);
    $after = $afterStmt->fetch();

    audit_log($user, 'orders.update', 'order', $orderId, $order, $after, [
        'adjustments' => $computedAdjustments,
    ]);

    $newCustomerId = (int) $newValues['customer_id'];
    $newBranchId = (int) $newValues['sub_branch_id'];
    $newStatus = (string) $newValues['fulfillment_status'];

    if ($newCustomerId === $previousCustomerId) {
        if (abs($previousTotal - $totalPrice) > 0.0001) {
            adjust_customer_balance($db, $newCustomerId, $previousTotal - $totalPrice);
            record_customer_balance(
                $db,
                $newCustomerId,
                $newBranchId ?: null,
                $previousTotal,
                'order_reversal',
                'order',
                $orderId,
                $user['id'] ?? null,
                'Order updated'
            );
            record_customer_balance(
                $db,
                $newCustomerId,
                $newBranchId ?: null,
                -$totalPrice,
                'order_charge',
                'order',
                $orderId,
                $user['id'] ?? null,
                'Order updated'
            );
        }
    } else {
        adjust_customer_balance($db, $previousCustomerId, $previousTotal);
        adjust_customer_balance($db, $newCustomerId, -$totalPrice);
        record_customer_balance(
            $db,
            $previousCustomerId,
            $previousBranchId ?: null,
            $previousTotal,
            'order_reversal',
            'order',
            $orderId,
            $user['id'] ?? null,
            'Order reassigned'
        );
        record_customer_balance(
            $db,
            $newCustomerId,
            $newBranchId ?: null,
            -$totalPrice,
            'order_charge',
            'order',
            $orderId,
            $user['id'] ?? null,
            'Order reassigned'
        );
    }

    if ($previousStatus !== 'received_subbranch' && $newStatus === 'received_subbranch') {
        record_branch_balance(
            $db,
            $newBranchId,
            $totalPrice,
            'order_received',
            'order',
            $orderId,
            $user['id'] ?? null,
            'Order received'
        );
    } elseif ($previousStatus === 'received_subbranch' && $newStatus !== 'received_subbranch') {
        record_branch_balance(
            $db,
            $previousBranchId,
            -$previousTotal,
            'order_reversal',
            'order',
            $orderId,
            $user['id'] ?? null,
            'Order status reversed'
        );
    } elseif ($previousStatus === 'received_subbranch' && $newStatus === 'received_subbranch') {
        if ($previousBranchId !== $newBranchId) {
            record_branch_balance(
                $db,
                $previousBranchId,
                -$previousTotal,
                'order_reversal',
                'order',
                $orderId,
                $user['id'] ?? null,
                'Order moved to another branch'
            );
            record_branch_balance(
                $db,
                $newBranchId,
                $totalPrice,
                'order_received',
                'order',
                $orderId,
                $user['id'] ?? null,
                'Order moved from another branch'
            );
        } elseif (abs($totalPrice - $previousTotal) > 0.0001) {
            record_branch_balance(
                $db,
                $newBranchId,
                $totalPrice - $previousTotal,
                'adjustment',
                'order',
                $orderId,
                $user['id'] ?? null,
                'Order total updated'
            );
        }
    }

    $newShipmentId = (int) $newValues['shipment_id'];
    update_shipment_totals($newShipmentId);
    if ($previousShipmentId !== $newShipmentId) {
        update_shipment_totals($previousShipmentId);
        if (($targetShipment['status'] ?? '') === 'distributed') {
            $db->prepare(
                'UPDATE shipments SET status = ?, updated_at = NOW(), updated_by_user_id = ? '
                . 'WHERE id = ? AND deleted_at IS NULL'
            )->execute(['partially_distributed', $user['id'] ?? null, $newShipmentId]);
        }
    }
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    if ((int) $e->getCode() === 23000) {
        api_error('Tracking number already exists for this shipment', 409);
    }
    api_error('Failed to update order', 500);
}

api_json(['ok' => true]);
