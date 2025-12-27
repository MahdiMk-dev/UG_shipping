<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';

api_require_method('GET');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);

$partnerId = api_int($_GET['id'] ?? ($_GET['partner_id'] ?? null));
if (!$partnerId) {
    api_error('partner_id is required', 422);
}

$db = db();
$stmt = $db->prepare(
    'SELECT p.*, c.name AS country_name '
    . 'FROM partner_profiles p '
    . 'LEFT JOIN countries c ON c.id = p.country_id '
    . 'WHERE p.id = ? AND p.deleted_at IS NULL'
);
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();
if (!$partner) {
    api_error('Partner profile not found', 404);
}

$role = $user['role'] ?? '';
if ($role === 'Warehouse') {
    $warehouseCountryId = get_branch_country_id($user);
    if (!$warehouseCountryId) {
        api_error('Warehouse country scope required', 403);
    }
    if ((int) ($partner['country_id'] ?? 0) !== (int) $warehouseCountryId) {
        api_error('Forbidden', 403);
    }
}

$shipmentsStmt = $db->prepare(
    'SELECT s.id, s.shipment_number, s.status, s.origin_country_id, c.name AS origin_country, '
    . 'CASE '
    . 'WHEN s.shipper_profile_id = ? THEN \'shipper\' '
    . 'WHEN s.consignee_profile_id = ? THEN \'consignee\' '
    . 'ELSE NULL '
    . 'END AS partner_role '
    . 'FROM shipments s '
    . 'LEFT JOIN countries c ON c.id = s.origin_country_id '
    . 'WHERE s.deleted_at IS NULL AND (s.shipper_profile_id = ? OR s.consignee_profile_id = ?) '
    . 'ORDER BY s.id DESC LIMIT 50'
);
$shipmentsStmt->execute([$partnerId, $partnerId, $partnerId, $partnerId]);
$shipments = $shipmentsStmt->fetchAll();

api_json(['ok' => true, 'partner' => $partner, 'shipments' => $shipments]);
