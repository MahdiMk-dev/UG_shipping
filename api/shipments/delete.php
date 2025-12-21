<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/api.php';
require_once __DIR__ . '/../../app/permissions.php';
require_once __DIR__ . '/../../app/audit.php';

api_require_method('POST');
$user = require_role(['Admin', 'Owner', 'Main Branch']);
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

$db->beginTransaction();
try {
    $stmt = $db->prepare(
        'UPDATE shipments SET deleted_at = NOW(), updated_at = NOW(), updated_by_user_id = ? '
        . 'WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$user['id'] ?? null, $shipmentId]);
    $afterStmt = $db->prepare('SELECT * FROM shipments WHERE id = ?');
    $afterStmt->execute([$shipmentId]);
    $after = $afterStmt->fetch();
    audit_log($user, 'shipments.delete', 'shipment', $shipmentId, $before, $after);
    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    api_error('Failed to delete shipment', 500);
}

api_json(['ok' => true]);
