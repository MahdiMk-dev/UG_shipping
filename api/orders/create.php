<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);
$input = api_read_input();

$shipmentId = api_int($input['shipment_id'] ?? null);
$customerId = api_int($input['customer_id'] ?? null);
$collectionId = api_int($input['collection_id'] ?? null);
$trackingNumber = api_string($input['tracking_number'] ?? null);
$deliveryType = api_string($input['delivery_type'] ?? null);
$unitType = api_string($input['unit_type'] ?? null);
$weightType = api_string($input['weight_type'] ?? null);
$rate = api_float($input['rate'] ?? null);
$note = api_string($input['note'] ?? null);

if (!$shipmentId || !$customerId || !$trackingNumber || !$deliveryType || !$unitType || !$weightType) {
    api_error('shipment_id, customer_id, tracking_number, delivery_type, unit_type, weight_type are required', 422);
}

$allowedDelivery = ['pickup', 'delivery'];
$allowedUnit = ['kg', 'cbm'];
$allowedWeight = ['actual', 'volumetric'];

if (!in_array($deliveryType, $allowedDelivery, true)) {
    api_error('Invalid delivery_type', 422);
}
if (!in_array($unitType, $allowedUnit, true)) {
    api_error('Invalid unit_type', 422);
}
if (!in_array($weightType, $allowedWeight, true)) {
    api_error('Invalid weight_type', 422);
}
if ($rate === null) {
    api_error('rate is required', 422);
}

$db = db();

$shipmentStmt = $db->prepare('SELECT id, status, origin_country_id FROM shipments WHERE id = ? AND deleted_at IS NULL');
$shipmentStmt->execute([$shipmentId]);
$shipment = $shipmentStmt->fetch();
if (!$shipment) {
    api_error('Shipment not found', 404);
}
$role = $user['role'] ?? '';
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
    api_error('Shipment must be active to create orders', 403);
}

$customerStmt = $db->prepare('SELECT id, sub_branch_id FROM customers WHERE id = ? AND deleted_at IS NULL');
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();
if (!$customer) {
    api_error('Customer not found', 404);
}

$subBranchId = $customer['sub_branch_id'] ?? null;
if (!$subBranchId) {
    api_error('Customer has no sub branch assigned', 422);
}

if ($collectionId) {
    $collectionStmt = $db->prepare('SELECT id FROM collections WHERE id = ? AND shipment_id = ?');
    $collectionStmt->execute([$collectionId, $shipmentId]);
    if (!$collectionStmt->fetch()) {
        api_error('Collection does not belong to this shipment', 422);
    }
}

$actualWeight = api_float($input['actual_weight'] ?? null);
$w = api_float($input['w'] ?? null);
$d = api_float($input['d'] ?? null);
$h = api_float($input['h'] ?? null);

if ($weightType === 'actual' && $actualWeight === null) {
    api_error('actual_weight is required for actual weight_type', 422);
}
if ($weightType === 'volumetric' && ($w === null || $d === null || $h === null)) {
    api_error('w, d, h are required for volumetric weight_type', 422);
}

$qty = compute_qty($unitType, $weightType, $actualWeight, $w, $d, $h);
$basePrice = compute_base_price($qty, $rate);

$adjustments = $input['adjustments'] ?? [];
if (!is_array($adjustments)) {
    api_error('adjustments must be an array', 422);
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

    $amount = $calcType === 'percentage' ? ($basePrice * ($value / 100)) : $value;
    if ($kind === 'discount') {
        $amount *= -1;
    }

    $normalizedAdjustments[] = [
        'title' => $title,
        'description' => api_string($adjustment['description'] ?? null),
        'kind' => $kind,
        'calc_type' => $calcType,
        'value' => $value,
        'computed_amount' => round($amount, 2),
    ];
}

$adjustmentsTotal = compute_adjustments_total($normalizedAdjustments, $basePrice);
$totalPrice = round($basePrice + $adjustmentsTotal, 2);

$fulfillmentStatus = 'in_shipment';
$notificationStatus = 'pending';

$db->beginTransaction();

try {
    $stmt = $db->prepare(
        'INSERT INTO orders '
        . '(shipment_id, customer_id, sub_branch_id, collection_id, tracking_number, delivery_type, '
        . 'unit_type, qty, weight_type, actual_weight, w, d, h, rate, base_price, adjustments_total, '
        . 'total_price, note, fulfillment_status, notification_status, created_by_user_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $shipmentId,
        $customerId,
        $subBranchId,
        $collectionId,
        $trackingNumber,
        $deliveryType,
        $unitType,
        $qty,
        $weightType,
        $actualWeight,
        $w,
        $d,
        $h,
        $rate,
        $basePrice,
        $adjustmentsTotal,
        $totalPrice,
        $note,
        $fulfillmentStatus,
        $notificationStatus,
        $user['id'] ?? null,
    ]);

    $orderId = (int) $db->lastInsertId();

    if (!empty($normalizedAdjustments)) {
        $adjStmt = $db->prepare(
            'INSERT INTO order_adjustments '
            . '(order_id, title, description, kind, calc_type, value, computed_amount, created_by_user_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($normalizedAdjustments as $adjustment) {
            $adjStmt->execute([
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

    $orderRowStmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $orderRowStmt->execute([$orderId]);
    $after = $orderRowStmt->fetch();
    audit_log($user, 'orders.create', 'order', $orderId, null, $after, [
        'adjustments' => $normalizedAdjustments,
    ]);
    update_shipment_totals($shipmentId);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    if ((int) $e->getCode() === 23000) {
        api_error('Tracking number already exists for this shipment', 409);
    }
    api_error('Failed to create order', 500);
}

api_json(['ok' => true, 'id' => $orderId]);
