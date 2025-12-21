<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch', 'Warehouse']);
$input = api_read_input();

$shipmentId = api_int($input['shipment_id'] ?? null);
$name = api_string($input['name'] ?? null);

if (!$shipmentId || !$name) {
    api_error('shipment_id and name are required', 422);
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
    api_error('Shipment must be active to create a collection', 403);
}

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'INSERT INTO collections (shipment_id, name, created_by_user_id) VALUES (?, ?, ?)'
    );
    $stmt->execute([$shipmentId, $name, $user['id'] ?? null]);
    $collectionId = (int) $db->lastInsertId();

    $rowStmt = $db->prepare('SELECT * FROM collections WHERE id = ?');
    $rowStmt->execute([$collectionId]);
    $after = $rowStmt->fetch();
    audit_log($user, 'collections.create', 'collection', $collectionId, null, $after);

    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to create collection', 500);
}

api_json(['ok' => true, 'id' => $collectionId]);
