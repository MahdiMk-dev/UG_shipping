<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';
require_once __DIR__ . '/../../app/services/shipment_service.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);
$input = api_read_input();

$shipmentNumber = api_string($input['shipment_number'] ?? null);
$originCountryId = api_int($input['origin_country_id'] ?? null);
$shippingType = api_string($input['shipping_type'] ?? null);
$status = api_string($input['status'] ?? 'active') ?? 'active';
$shipperProfileId = api_int($input['shipper_profile_id'] ?? null);
$consigneeProfileId = api_int($input['consignee_profile_id'] ?? null);
$typeOfGoods = api_string($input['type_of_goods'] ?? null);
$shipperName = api_string($input['shipper'] ?? null);
$consigneeName = api_string($input['consignee'] ?? null);

if (($user['role'] ?? '') === 'Warehouse' && ($shipperProfileId || $consigneeProfileId || $shipperName || $consigneeName)) {
    api_error('Shipper/consignee fields are restricted for warehouse users', 403);
}

$db = db();
$warehouseCountryId = null;
if (($user['role'] ?? '') === 'Warehouse') {
    $status = 'active';
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if (!$originCountryId) {
        $originCountryId = $warehouseCountryId;
    }
    if ((int) $originCountryId !== (int) $warehouseCountryId) {
        api_error('Origin country must match warehouse country', 403);
    }
}

if (!$shipmentNumber || !$originCountryId || !$shippingType) {
    api_error('shipment_number, origin_country_id, and shipping_type are required', 422);
}
if (!$typeOfGoods) {
    api_error('type_of_goods is required', 422);
}
$goodsStmt = $db->prepare('SELECT id FROM goods_types WHERE name = ? AND deleted_at IS NULL');
$goodsStmt->execute([$typeOfGoods]);
if (!$goodsStmt->fetch()) {
    api_error('type_of_goods must match a configured goods type', 422);
}

$allowedStatus = ['active', 'departed', 'airport', 'arrived', 'partially_distributed', 'distributed'];
if (!in_array($status, $allowedStatus, true)) {
    api_error('Invalid status', 422);
}

$allowedTypes = ['air', 'sea', 'land'];
if (!in_array($shippingType, $allowedTypes, true)) {
    api_error('Invalid shipping_type', 422);
}

$SupplierStmt = $db->prepare(
    'SELECT id, type FROM supplier_profiles WHERE id = ? AND deleted_at IS NULL'
);
if ($shipperProfileId) {
    $SupplierStmt->execute([$shipperProfileId]);
    $shipperProfile = $SupplierStmt->fetch();
    if (!$shipperProfile) {
        api_error('Shipper profile not found', 404);
    }
    if (($shipperProfile['type'] ?? '') !== 'shipper') {
        api_error('Selected shipper profile is invalid', 422);
    }
}
if ($consigneeProfileId) {
    $SupplierStmt->execute([$consigneeProfileId]);
    $consigneeProfile = $SupplierStmt->fetch();
    if (!$consigneeProfile) {
        api_error('Consignee profile not found', 404);
    }
    if (($consigneeProfile['type'] ?? '') !== 'consignee') {
        api_error('Selected consignee profile is invalid', 422);
    }
}

$optionalFields = [
    'shipper',
    'consignee',
    'shipment_date',
    'way_of_shipment',
    'vessel_or_flight_name',
    'departure_date',
    'arrival_date',
    'note',
];

$values = [];
foreach ($optionalFields as $field) {
    $values[$field] = api_string($input[$field] ?? null);
}
$values['type_of_goods'] = $typeOfGoods;

$departureDate = $values['departure_date'] ?? null;
$arrivalDate = $values['arrival_date'] ?? null;

if ($departureDate !== null && strtotime($departureDate) === false) {
    api_error('Invalid departure_date', 422);
}
if ($arrivalDate !== null && strtotime($arrivalDate) === false) {
    api_error('Invalid arrival_date', 422);
}
if ($arrivalDate !== null) {
    $today = strtotime(date('Y-m-d'));
    $arrivalTs = strtotime($arrivalDate);
    if ($arrivalTs < $today) {
        api_error('arrival_date must be today or later', 422);
    }
    if ($departureDate !== null) {
        $departureTs = strtotime($departureDate);
        if ($arrivalTs <= $departureTs) {
            api_error('arrival_date must be greater than departure_date', 422);
        }
    }
}

$numericFields = [
    'size',
    'weight',
    'gross_weight',
];
foreach ($numericFields as $field) {
    $values[$field] = api_float($input[$field] ?? null);
}
if (!array_key_exists('cost_per_unit', $values)) {
    $values['cost_per_unit'] = null;
}

$defaultRateKg = api_float($input['default_rate_kg'] ?? null);
$defaultRateCbm = api_float($input['default_rate_cbm'] ?? null);
$legacyDefaultRate = api_float($input['default_rate'] ?? null);
if ($defaultRateKg === null && $defaultRateCbm === null && $legacyDefaultRate !== null) {
    $defaultRateKg = $legacyDefaultRate;
    $defaultRateCbm = $legacyDefaultRate;
}
if ($defaultRateKg === null && $defaultRateCbm !== null) {
    $defaultRateKg = $defaultRateCbm;
}
if ($defaultRateCbm === null && $defaultRateKg !== null) {
    $defaultRateCbm = $defaultRateKg;
}
if ($defaultRateKg === null) {
    $defaultRateKg = 0.0;
}
if ($defaultRateCbm === null) {
    $defaultRateCbm = 0.0;
}
$values['default_rate'] = $legacyDefaultRate ?? $defaultRateKg;

$defaultRateUnit = api_string($input['default_rate_unit'] ?? null);
if ($defaultRateUnit !== null && !in_array($defaultRateUnit, ['kg', 'cbm'], true)) {
    api_error('Invalid default_rate_unit', 422);
}
if (($user['role'] ?? '') === 'Warehouse') {
    $values['default_rate'] = 0.0;
    $defaultRateKg = 0.0;
    $defaultRateCbm = 0.0;
    $values['cost_per_unit'] = null;
    $defaultRateUnit = null;
}

$stmt = $db->prepare(
    'INSERT INTO shipments '
    . '(shipment_number, origin_country_id, status, shipping_type, shipper, consignee, shipper_profile_id, '
    . 'consignee_profile_id, shipment_date, '
    . 'way_of_shipment, type_of_goods, vessel_or_flight_name, departure_date, arrival_date, size, weight, '
    . 'gross_weight, default_rate, default_rate_kg, default_rate_cbm, default_rate_unit, cost_per_unit, '
    . 'note, created_by_user_id) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

try {
    $db->beginTransaction();
    $stmt->execute([
        $shipmentNumber,
        $originCountryId,
        $status,
        $shippingType,
        $values['shipper'],
        $values['consignee'],
        $shipperProfileId,
        $consigneeProfileId,
        $values['shipment_date'],
        $values['way_of_shipment'],
        $values['type_of_goods'],
        $values['vessel_or_flight_name'],
        $values['departure_date'],
        $values['arrival_date'],
        $values['size'],
        $values['weight'],
        $values['gross_weight'],
        $values['default_rate'],
        $defaultRateKg,
        $defaultRateCbm,
        $defaultRateUnit,
        $values['cost_per_unit'],
        $values['note'],
        $user['id'] ?? null,
    ]);
    $shipmentId = (int) $db->lastInsertId();
    $rowStmt = $db->prepare('SELECT * FROM shipments WHERE id = ?');
    $rowStmt->execute([$shipmentId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'shipments.create', 'shipment', $shipmentId, null, $after);
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if ((int) $e->getCode() === 23000) {
        api_error('Shipment number already exists', 409);
    }
    api_error('Failed to create shipment', 500);
}

update_shipment_cost_per_unit($shipmentId);

api_json(['ok' => true, 'id' => $shipmentId]);


