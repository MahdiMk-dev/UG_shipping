<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);
$input = api_read_input();

$shipmentId = api_int($input['shipment_id'] ?? ($input['id'] ?? null));
if (!$shipmentId) {
    api_error('shipment_id is required', 422);
}

$db = db();
$beforeStmt = $db->prepare('SELECT * FROM shipments WHERE id = ? AND deleted_at IS NULL');
$beforeStmt->execute([$shipmentId]);
$before = $beforeStmt->fetch();
if (!$before) {
    api_error('Shipment not found', 404);
}
$role = $user['role'] ?? '';
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if ((int) ($before['origin_country_id'] ?? 0) !== (int) $warehouseCountryId) {
        api_error('Forbidden', 403);
    }
}
if ($role === 'Warehouse' && ($before['status'] ?? '') !== 'active') {
    api_error('Shipment must be active to edit', 403);
}

$newDepartureDate = array_key_exists('departure_date', $input)
    ? api_string($input['departure_date'] ?? null)
    : ($before['departure_date'] ?? null);
$newArrivalDate = array_key_exists('arrival_date', $input)
    ? api_string($input['arrival_date'] ?? null)
    : ($before['arrival_date'] ?? null);

if ($newDepartureDate !== null && strtotime($newDepartureDate) === false) {
    api_error('Invalid departure_date', 422);
}
if ($newArrivalDate !== null && strtotime($newArrivalDate) === false) {
    api_error('Invalid arrival_date', 422);
}
if ($newArrivalDate !== null) {
    $today = strtotime(date('Y-m-d'));
    $arrivalTs = strtotime($newArrivalDate);
    if ($arrivalTs < $today) {
        api_error('arrival_date must be today or later', 422);
    }
    if ($newDepartureDate !== null) {
        $departureTs = strtotime($newDepartureDate);
        if ($arrivalTs <= $departureTs) {
            api_error('arrival_date must be greater than departure_date', 422);
        }
    }
}

$allowedStatus = ['active', 'departed', 'airport', 'arrived', 'distributed'];
$allowedTypes = ['air', 'sea', 'land'];

$fields = [];
$params = [];
$statusChangedToArrived = false;
$statusChangedToInShipment = false;

if (array_key_exists('shipment_number', $input)) {
    $shipmentNumber = api_string($input['shipment_number'] ?? null);
    if (!$shipmentNumber) {
        api_error('shipment_number cannot be empty', 422);
    }
    $fields[] = 'shipment_number = ?';
    $params[] = $shipmentNumber;
}

if (array_key_exists('origin_country_id', $input)) {
    $originCountryId = api_int($input['origin_country_id'] ?? null);
    if (!$originCountryId) {
        api_error('origin_country_id is invalid', 422);
    }
    if ($role === 'Warehouse') {
        $warehouseCountryId = get_branch_country_id($user);
        if (!$warehouseCountryId) {
            api_error('Warehouse country scope required', 403);
        }
        if ((int) $originCountryId !== (int) $warehouseCountryId) {
            api_error('Origin country must match warehouse country', 403);
        }
    }
    $fields[] = 'origin_country_id = ?';
    $params[] = $originCountryId;
}

if (array_key_exists('status', $input)) {
    $status = api_string($input['status'] ?? null);
    if (!$status || !in_array($status, $allowedStatus, true)) {
        api_error('Invalid status', 422);
    }
    if (($before['status'] ?? '') === 'distributed' && $status !== 'distributed') {
        api_error('Cannot update status after shipment is distributed', 422);
    }
    $fields[] = 'status = ?';
    $params[] = $status;
    $statusChangedToArrived = $status === 'arrived' && ($before['status'] ?? '') !== 'arrived';
    $statusChangedToInShipment = in_array($status, ['active', 'airport'], true)
        && ($before['status'] ?? '') !== $status;
}

if (array_key_exists('shipping_type', $input)) {
    $shippingType = api_string($input['shipping_type'] ?? null);
    if (!$shippingType || !in_array($shippingType, $allowedTypes, true)) {
        api_error('Invalid shipping_type', 422);
    }
    $fields[] = 'shipping_type = ?';
    $params[] = $shippingType;
}

$optionalText = [
    'shipper',
    'consignee',
    'shipment_date',
    'way_of_shipment',
    'type_of_goods',
    'vessel_or_flight_name',
    'departure_date',
    'arrival_date',
    'note',
];

foreach ($optionalText as $field) {
    if (array_key_exists($field, $input)) {
        $fields[] = $field . ' = ?';
        $params[] = api_string($input[$field] ?? null);
    }
}

$optionalNumbers = [
    'size',
    'weight',
    'gross_weight',
    'default_rate',
    'cost_per_unit',
];
foreach ($optionalNumbers as $field) {
    if (array_key_exists($field, $input)) {
        $fields[] = $field . ' = ?';
        $params[] = api_float($input[$field] ?? null);
    }
}

if (array_key_exists('default_rate_unit', $input)) {
    $unit = api_string($input['default_rate_unit'] ?? null);
    if ($unit !== null && !in_array($unit, ['kg', 'cbm'], true)) {
        api_error('Invalid default_rate_unit', 422);
    }
    $fields[] = 'default_rate_unit = ?';
    $params[] = $unit;
}

if (array_key_exists('default_rate', $input)) {
    $newDefaultRate = api_float($input['default_rate'] ?? null);
    $oldDefaultRate = $before['default_rate'] !== null ? (float) $before['default_rate'] : null;
    $formattedOldRate = $oldDefaultRate !== null ? number_format($oldDefaultRate, 2, '.', '') : null;
    $formattedNewRate = $newDefaultRate !== null ? number_format($newDefaultRate, 2, '.', '') : null;
    $syncOrderRates = $formattedOldRate !== null
        && $formattedNewRate !== null
        && $formattedOldRate !== $formattedNewRate;
} else {
    $syncOrderRates = false;
    $newDefaultRate = null;
    $formattedOldRate = null;
}

if (empty($fields)) {
    api_error('No fields to update', 422);
}

$fields[] = 'updated_at = NOW()';
$fields[] = 'updated_by_user_id = ?';
$params[] = $user['id'] ?? null;

$params[] = $shipmentId;

$sql = 'UPDATE shipments SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';

try {
    $db->beginTransaction();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if ($syncOrderRates) {
        $ordersStmt = $db->prepare(
            'SELECT id, qty FROM orders WHERE shipment_id = ? AND deleted_at IS NULL AND rate = ?'
        );
        $ordersStmt->execute([$shipmentId, $formattedOldRate]);
        $orders = $ordersStmt->fetchAll() ?: [];

        if (!empty($orders)) {
            $adjStmt = $db->prepare(
                'SELECT id, kind, calc_type, value FROM order_adjustments '
                . 'WHERE order_id = ? AND deleted_at IS NULL'
            );
            $updateOrder = $db->prepare(
                'UPDATE orders SET rate = ?, base_price = ?, adjustments_total = ?, total_price = ?, '
                . 'updated_at = NOW(), updated_by_user_id = ? WHERE id = ? AND deleted_at IS NULL'
            );
            $updateAdjustment = $db->prepare(
                'UPDATE order_adjustments SET computed_amount = ? WHERE id = ?'
            );

            foreach ($orders as $order) {
                $qty = (float) ($order['qty'] ?? 0);
                $basePrice = compute_base_price($qty, (float) $formattedNewRate);
                $adjStmt->execute([$order['id']]);
                $adjustments = $adjStmt->fetchAll() ?: [];

                $computedAdjustments = [];
                foreach ($adjustments as $adjustment) {
                    $calcType = $adjustment['calc_type'] ?? 'amount';
                    $kind = $adjustment['kind'] ?? 'cost';
                    $value = (float) ($adjustment['value'] ?? 0);

                    $amount = $calcType === 'percentage'
                        ? ($basePrice * ($value / 100))
                        : $value;
                    if ($kind === 'discount') {
                        $amount *= -1;
                    }
                    $computedAmount = round($amount, 2);
                    $computedAdjustments[] = [
                        'calc_type' => $calcType,
                        'kind' => $kind,
                        'value' => $value,
                    ];
                    $updateAdjustment->execute([$computedAmount, $adjustment['id']]);
                }

                $adjustmentsTotal = compute_adjustments_total($computedAdjustments, $basePrice);
                $totalPrice = round($basePrice + $adjustmentsTotal, 2);

                $updateOrder->execute([
                    $formattedNewRate,
                    $basePrice,
                    $adjustmentsTotal,
                    $totalPrice,
                    $user['id'] ?? null,
                    $order['id'],
                ]);
            }
        }
    }

    if ($statusChangedToArrived) {
        $ordersStmt = $db->prepare(
            'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE shipment_id = ? AND deleted_at IS NULL AND fulfillment_status = ?'
        );
        $ordersStmt->execute(['main_branch', $user['id'] ?? null, $shipmentId, 'in_shipment']);
    }
    if ($statusChangedToInShipment) {
        $ordersStmt = $db->prepare(
            'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE shipment_id = ? AND deleted_at IS NULL '
            . "AND fulfillment_status IN ('main_branch', 'pending_receipt', 'received_subbranch')"
        );
        $ordersStmt->execute(['in_shipment', $user['id'] ?? null, $shipmentId]);
    }

    $afterStmt = $db->prepare('SELECT * FROM shipments WHERE id = ?');
    $afterStmt->execute([$shipmentId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'shipments.update', 'shipment', $shipmentId, $before, $after);
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if ((int) $e->getCode() === 23000) {
        api_error('Shipment number already exists', 409);
    }
    api_error('Failed to update shipment', 500);
}

api_json(['ok' => true]);
