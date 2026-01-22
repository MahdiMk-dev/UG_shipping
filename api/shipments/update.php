<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';
require_once __DIR__ . '/../../app/services/balance_service.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/company.php';

api_require_method('PATCH');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);
$input = api_read_input();

$legacyDefaultRate = array_key_exists('default_rate', $input)
    ? api_float($input['default_rate'] ?? null)
    : null;
if ($legacyDefaultRate !== null
    && !array_key_exists('default_rate_kg', $input)
    && !array_key_exists('default_rate_cbm', $input)) {
    $input['default_rate_kg'] = $legacyDefaultRate;
    $input['default_rate_cbm'] = $legacyDefaultRate;
}

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
$warehouseCountryId = null;
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
if ($role === 'Warehouse') {
    $blockedFields = [
        'default_rate',
        'default_rate_kg',
        'default_rate_cbm',
        'default_rate_unit',
        'cost_per_unit',
    ];
    foreach ($blockedFields as $blockedField) {
        if (array_key_exists($blockedField, $input)) {
            api_error('Rate fields are restricted for warehouse users', 403);
        }
    }
    $blockedSupplierFields = ['shipper_profile_id', 'consignee_profile_id', 'shipper', 'consignee'];
    foreach ($blockedSupplierFields as $blockedField) {
        if (array_key_exists($blockedField, $input)) {
            api_error('Shipper/consignee fields are restricted for warehouse users', 403);
        }
    }
}

$rateChangeRequested = false;
if (array_key_exists('default_rate', $input)) {
    $newRate = api_float($input['default_rate'] ?? null);
    $oldRate = $before['default_rate'] !== null ? (float) $before['default_rate'] : null;
    if ($newRate !== null || $oldRate !== null) {
        $rateChangeRequested = $rateChangeRequested || $newRate === null || $oldRate === null
            || abs($newRate - $oldRate) > 0.0001;
    }
}
if (array_key_exists('default_rate_kg', $input)) {
    $newRate = api_float($input['default_rate_kg'] ?? null);
    $oldRate = $before['default_rate_kg'] !== null ? (float) $before['default_rate_kg'] : null;
    if ($newRate !== null || $oldRate !== null) {
        $rateChangeRequested = $rateChangeRequested || $newRate === null || $oldRate === null
            || abs($newRate - $oldRate) > 0.0001;
    }
}
if (array_key_exists('default_rate_cbm', $input)) {
    $newRate = api_float($input['default_rate_cbm'] ?? null);
    $oldRate = $before['default_rate_cbm'] !== null ? (float) $before['default_rate_cbm'] : null;
    if ($newRate !== null || $oldRate !== null) {
        $rateChangeRequested = $rateChangeRequested || $newRate === null || $oldRate === null
            || abs($newRate - $oldRate) > 0.0001;
    }
}
if (array_key_exists('default_rate_unit', $input)) {
    $newUnit = api_string($input['default_rate_unit'] ?? null);
    $oldUnit = $before['default_rate_unit'] !== null ? (string) $before['default_rate_unit'] : null;
    if ($newUnit !== null || $oldUnit !== null) {
        $rateChangeRequested = $rateChangeRequested || $newUnit !== $oldUnit;
    }
}

if ($rateChangeRequested) {
    $rateLockStmt = $db->prepare(
        'SELECT 1 FROM invoice_items ii '
        . 'JOIN invoices i ON i.id = ii.invoice_id '
        . 'JOIN orders o ON o.id = ii.order_id '
        . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL '
        . 'AND i.deleted_at IS NULL AND i.status <> \'void\' '
        . 'LIMIT 1'
    );
    $rateLockStmt->execute([$shipmentId]);
    if ($rateLockStmt->fetchColumn()) {
        api_error('Shipment has invoiced orders. Rate updates are locked.', 409);
    }
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

$allowedStatus = ['active', 'departed', 'airport', 'arrived', 'partially_distributed', 'distributed'];
$allowedTypes = ['air', 'sea', 'land'];
$receivedStatuses = ['received_subbranch', 'with_delivery', 'picked_up'];

$fields = [];
$params = [];
$statusChangedToInShipment = false;

if (array_key_exists('shipment_number', $input)) {
    $shipmentNumber = api_string($input['shipment_number'] ?? null);
    if (!$shipmentNumber) {
        api_error('shipment_number cannot be empty', 422);
    }
    $beforeShipmentNumber = api_string($before['shipment_number'] ?? null) ?? '';
    if ($shipmentNumber !== $beforeShipmentNumber) {
        $fields[] = 'shipment_number = ?';
        $params[] = $shipmentNumber;
    }
}

if (array_key_exists('origin_country_id', $input)) {
    $originCountryId = api_int($input['origin_country_id'] ?? null);
    if (!$originCountryId) {
        api_error('origin_country_id is invalid', 422);
    }
    $countryStmt = $db->prepare('SELECT id FROM countries WHERE id = ?');
    $countryStmt->execute([$originCountryId]);
    if (!$countryStmt->fetch()) {
        api_error('Origin country not found', 422);
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
    if (($before['status'] ?? '') === 'distributed' && !in_array($status, ['distributed', 'partially_distributed'], true)) {
        api_error('Cannot update status after shipment is distributed', 422);
    }
    $fields[] = 'status = ?';
    $params[] = $status;
    $statusChangedToInShipment = in_array($status, ['active', 'airport'], true)
        && ($before['status'] ?? '') !== $status;
    if ($status === 'departed' && empty($before['actual_departure_date'])) {
        $fields[] = 'actual_departure_date = CURDATE()';
    }
    if ($status === 'arrived' && empty($before['actual_arrival_date'])) {
        $fields[] = 'actual_arrival_date = CURDATE()';
    }
}

if (array_key_exists('shipping_type', $input)) {
    $shippingType = api_string($input['shipping_type'] ?? null);
    if (!$shippingType || !in_array($shippingType, $allowedTypes, true)) {
        api_error('Invalid shipping_type', 422);
    }
    $fields[] = 'shipping_type = ?';
    $params[] = $shippingType;
}

$SupplierStmt = $db->prepare(
    'SELECT id, type FROM supplier_profiles WHERE id = ? AND deleted_at IS NULL'
);
if (array_key_exists('shipper_profile_id', $input)) {
    $rawShipperProfileId = $input['shipper_profile_id'] ?? null;
    $shipperProfileId = ($rawShipperProfileId === '' || $rawShipperProfileId === null)
        ? null
        : api_int($rawShipperProfileId);
    if ($shipperProfileId !== null) {
        $SupplierStmt->execute([$shipperProfileId]);
        $shipperProfile = $SupplierStmt->fetch();
        if (!$shipperProfile) {
            api_error('Shipper profile not found', 404);
        }
        if (($shipperProfile['type'] ?? '') !== 'shipper') {
            api_error('Selected shipper profile is invalid', 422);
        }
    }
    $fields[] = 'shipper_profile_id = ?';
    $params[] = $shipperProfileId;
}

if (array_key_exists('consignee_profile_id', $input)) {
    $rawConsigneeProfileId = $input['consignee_profile_id'] ?? null;
    $consigneeProfileId = ($rawConsigneeProfileId === '' || $rawConsigneeProfileId === null)
        ? null
        : api_int($rawConsigneeProfileId);
    if ($consigneeProfileId !== null) {
        $SupplierStmt->execute([$consigneeProfileId]);
        $consigneeProfile = $SupplierStmt->fetch();
        if (!$consigneeProfile) {
            api_error('Consignee profile not found', 404);
        }
        if (($consigneeProfile['type'] ?? '') !== 'consignee') {
            api_error('Selected consignee profile is invalid', 422);
        }
    }
    $fields[] = 'consignee_profile_id = ?';
    $params[] = $consigneeProfileId;
}

$optionalText = [
    'shipper',
    'consignee',
    'shipment_date',
    'way_of_shipment',
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

if (array_key_exists('type_of_goods', $input)) {
    $typeOfGoods = api_string($input['type_of_goods'] ?? null);
    if (!$typeOfGoods) {
        api_error('type_of_goods cannot be empty', 422);
    }
    if ($typeOfGoods !== ($before['type_of_goods'] ?? '')) {
        $goodsStmt = $db->prepare('SELECT id FROM goods_types WHERE name = ? AND deleted_at IS NULL');
        $goodsStmt->execute([$typeOfGoods]);
        if (!$goodsStmt->fetch()) {
            api_error('type_of_goods must match a configured goods type', 422);
        }
    }
    $fields[] = 'type_of_goods = ?';
    $params[] = $typeOfGoods;
}

$optionalNumbers = [
    'size',
    'weight',
    'gross_weight',
    'default_rate',
    'default_rate_kg',
    'default_rate_cbm',
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

$syncRates = [];
$newDefaultRateKg = null;
$newDefaultRateCbm = null;
$formattedOldRateKg = null;
$formattedOldRateCbm = null;
$formattedNewRateKg = null;
$formattedNewRateCbm = null;

if (array_key_exists('default_rate_kg', $input)) {
    $newDefaultRateKg = api_float($input['default_rate_kg'] ?? null);
    $oldDefaultRateKg = $before['default_rate_kg'] !== null ? (float) $before['default_rate_kg'] : null;
    $formattedOldRateKg = $oldDefaultRateKg !== null ? number_format($oldDefaultRateKg, 2, '.', '') : null;
    $formattedNewRateKg = $newDefaultRateKg !== null ? number_format($newDefaultRateKg, 2, '.', '') : null;
    if ($formattedOldRateKg !== null
        && $formattedNewRateKg !== null
        && $formattedOldRateKg !== $formattedNewRateKg) {
        $syncRates[] = [
            'rate_field' => 'rate_kg',
            'weight_type' => 'actual',
            'old_rate' => $formattedOldRateKg,
            'new_rate' => $formattedNewRateKg,
        ];
    }
}

if (array_key_exists('default_rate_cbm', $input)) {
    $newDefaultRateCbm = api_float($input['default_rate_cbm'] ?? null);
    $oldDefaultRateCbm = $before['default_rate_cbm'] !== null ? (float) $before['default_rate_cbm'] : null;
    $formattedOldRateCbm = $oldDefaultRateCbm !== null ? number_format($oldDefaultRateCbm, 2, '.', '') : null;
    $formattedNewRateCbm = $newDefaultRateCbm !== null ? number_format($newDefaultRateCbm, 2, '.', '') : null;
    if ($formattedOldRateCbm !== null
        && $formattedNewRateCbm !== null
        && $formattedOldRateCbm !== $formattedNewRateCbm) {
        $syncRates[] = [
            'rate_field' => 'rate_cbm',
            'weight_type' => 'volumetric',
            'old_rate' => $formattedOldRateCbm,
            'new_rate' => $formattedNewRateCbm,
        ];
    }
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
    $pointsSettings = company_points_settings();
    $pointsPrice = (float) ($pointsSettings['points_price'] ?? 0);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if (!empty($syncRates)) {
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

        foreach ($syncRates as $sync) {
            $rateField = $sync['rate_field'];
            $ordersStmt = $db->prepare(
                'SELECT o.id, o.qty, o.total_price, o.customer_id, o.sub_branch_id, '
                . 'o.fulfillment_status, o.weight_type, o.rate_kg, o.rate_cbm '
                . 'FROM orders o '
                . 'WHERE o.shipment_id = ? AND o.deleted_at IS NULL AND o.' . $rateField . ' = ? '
                . 'AND NOT EXISTS ('
                . 'SELECT 1 FROM invoice_items ii '
                . 'JOIN invoices i ON i.id = ii.invoice_id '
                . 'WHERE ii.order_id = o.id AND i.deleted_at IS NULL AND i.status <> \'void\''
                . ')'
            );
            $ordersStmt->execute([$shipmentId, $sync['old_rate']]);
            $orders = $ordersStmt->fetchAll() ?: [];

            foreach ($orders as $order) {
                $rateKg = $order['rate_kg'] !== null ? (float) $order['rate_kg'] : null;
                $rateCbm = $order['rate_cbm'] !== null ? (float) $order['rate_cbm'] : null;
                if ($rateKg === null && $order['rate'] !== null) {
                    $rateKg = (float) $order['rate'];
                }
                if ($rateCbm === null && $order['rate'] !== null) {
                    $rateCbm = (float) $order['rate'];
                }
                if ($rateField === 'rate_kg') {
                    $rateKg = (float) $sync['new_rate'];
                }
                if ($rateField === 'rate_cbm') {
                    $rateCbm = (float) $sync['new_rate'];
                }

                $updateRateSql = sprintf(
                    'UPDATE orders SET %s = ?, updated_at = NOW(), updated_by_user_id = ? '
                    . 'WHERE id = ? AND deleted_at IS NULL',
                    $rateField
                );
                $db->prepare($updateRateSql)->execute([
                    $sync['new_rate'],
                    $user['id'] ?? null,
                    $order['id'],
                ]);

                if (($order['weight_type'] ?? '') !== $sync['weight_type']) {
                    continue;
                }

                $effectiveRate = ($order['weight_type'] ?? '') === 'volumetric'
                    ? (float) ($rateCbm ?? 0)
                    : (float) ($rateKg ?? 0);
                $qty = (float) ($order['qty'] ?? 0);
                $basePrice = compute_base_price($qty, $effectiveRate);
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
                    $effectiveRate,
                    $basePrice,
                    $adjustmentsTotal,
                    $totalPrice,
                    $user['id'] ?? null,
                    $order['id'],
                ]);

                $previousTotal = (float) ($order['total_price'] ?? 0);
                if (in_array(($order['fulfillment_status'] ?? ''), $receivedStatuses, true)
                    && abs($totalPrice - $previousTotal) > 0.0001) {
                    $customerId = (int) ($order['customer_id'] ?? 0);
                    $orderBranchId = (int) ($order['sub_branch_id'] ?? 0);
                    $delta = $totalPrice - $previousTotal;
                    adjust_customer_balance($db, $customerId, $delta);
                    adjust_customer_points_for_amount($db, $customerId, $delta, $pointsPrice);
                    record_customer_balance(
                        $db,
                        $customerId,
                        $orderBranchId ?: null,
                        -$previousTotal,
                        'order_reversal',
                        'order',
                        (int) $order['id'],
                        $user['id'] ?? null,
                        'Shipment rate updated'
                    );
                    record_customer_balance(
                        $db,
                        $customerId,
                        $orderBranchId ?: null,
                        $totalPrice,
                        'order_charge',
                        'order',
                        (int) $order['id'],
                        $user['id'] ?? null,
                        'Shipment rate updated'
                    );
                    record_branch_balance(
                        $db,
                        $orderBranchId,
                        $delta,
                        'adjustment',
                        'order',
                        (int) $order['id'],
                        $user['id'] ?? null,
                        'Shipment rate updated'
                    );
                }
            }
        }
    }

    $costNeedsUpdate =
        array_key_exists('weight', $input)
        || array_key_exists('size', $input)
        || array_key_exists('default_rate_unit', $input);

    if ($statusChangedToInShipment) {
        $placeholders = implode(',', array_fill(0, count($receivedStatuses), '?'));
        $receivedStmt = $db->prepare(
            'SELECT id, customer_id, sub_branch_id, total_price FROM orders '
            . 'WHERE shipment_id = ? AND deleted_at IS NULL '
            . "AND fulfillment_status IN ($placeholders)"
        );
        $receivedStmt->execute(array_merge([$shipmentId], $receivedStatuses));
        $receivedOrders = $receivedStmt->fetchAll() ?: [];
        foreach ($receivedOrders as $order) {
            $totalPrice = (float) ($order['total_price'] ?? 0);
            $customerId = (int) ($order['customer_id'] ?? 0);
            adjust_customer_balance($db, $customerId, -$totalPrice);
            adjust_customer_points_for_amount($db, $customerId, -$totalPrice, $pointsPrice);
            record_customer_balance(
                $db,
                $customerId,
                !empty($order['sub_branch_id']) ? (int) $order['sub_branch_id'] : null,
                -$totalPrice,
                'order_reversal',
                'order',
                (int) $order['id'],
                $user['id'] ?? null,
                'Shipment status reset'
            );
            record_branch_balance(
                $db,
                (int) ($order['sub_branch_id'] ?? 0),
                -$totalPrice,
                'order_reversal',
                'order',
                (int) $order['id'],
                $user['id'] ?? null,
                'Shipment status reset'
            );
        }

        $ordersStmt = $db->prepare(
            'UPDATE orders SET fulfillment_status = ?, updated_at = NOW(), updated_by_user_id = ? '
            . 'WHERE shipment_id = ? AND deleted_at IS NULL '
            . "AND fulfillment_status IN ('main_branch', 'pending_receipt', 'received_subbranch', 'with_delivery', 'picked_up')"
        );
        $ordersStmt->execute(['in_shipment', $user['id'] ?? null, $shipmentId]);
    }

    if ($costNeedsUpdate) {
        update_shipment_cost_per_unit($shipmentId);
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
    $errorInfo = $e->errorInfo ?? [];
    $sqlState = $errorInfo[0] ?? null;
    $driverCode = isset($errorInfo[1]) ? (int) $errorInfo[1] : null;
    if ($sqlState === '23000') {
        if ($driverCode === 1062) {
            api_error('Shipment number already exists', 409);
        }
        if ($driverCode === 1452) {
            $detail = $errorInfo[2] ?? '';
            $constraint = null;
            if ($detail && preg_match('/CONSTRAINT `([^`]+)`/', $detail, $matches)) {
                $constraint = $matches[1];
            }
            $fkMessages = [
                'fk_shipments_origin_country' => 'origin_country_id does not exist',
                'fk_shipments_shipper_profile' => 'shipper_profile_id does not exist',
                'fk_shipments_consignee_profile' => 'consignee_profile_id does not exist',
            ];
            if ($constraint && isset($fkMessages[$constraint])) {
                api_error($fkMessages[$constraint], 422);
            }
            api_error('Invalid reference data for shipment update', 422);
        }
        api_error('Shipment update violates a database constraint', 409);
    }
    api_error('Failed to update shipment', 500);
}

api_json(['ok' => true]);


