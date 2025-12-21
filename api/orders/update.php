<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
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

if ($role === 'Warehouse' && (int) $newValues['shipment_id'] !== (int) $order['shipment_id']) {
    $targetStmt = $db->prepare('SELECT status, origin_country_id FROM shipments WHERE id = ? AND deleted_at IS NULL');
    $targetStmt->execute([(int) $newValues['shipment_id']]);
    $targetShipment = $targetStmt->fetch();
    if (!$targetShipment) {
        api_error('Shipment not found', 404);
    }
    if ((int) ($targetShipment['origin_country_id'] ?? 0) !== (int) $warehouseCountryId) {
        api_error('Forbidden', 403);
    }
    if (($targetShipment['status'] ?? '') !== 'active') {
        api_error('Shipment must be active to edit orders', 403);
    }
}

$customerIdUpdated = array_key_exists('customer_id', $input);
if ($customerIdUpdated) {
    $customerStmt = $db->prepare('SELECT id, sub_branch_id FROM customers WHERE id = ? AND deleted_at IS NULL');
    $customerStmt->execute([$newValues['customer_id']]);
    $customer = $customerStmt->fetch();
    if (!$customer || empty($customer['sub_branch_id'])) {
        api_error('Customer has no sub branch assigned', 422);
    }
    $fields[] = 'sub_branch_id = ?';
    $params[] = $customer['sub_branch_id'];
    $newValues['sub_branch_id'] = $customer['sub_branch_id'];
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

    $newShipmentId = (int) $newValues['shipment_id'];
    update_shipment_totals($newShipmentId);
    if ($previousShipmentId !== $newShipmentId) {
        update_shipment_totals($previousShipmentId);
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
